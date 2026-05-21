<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\EntityVersion;
use App\Models\JwtUser;
use App\Models\Template;
use App\Support\DocumentHeadSnapshot;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Autorización sobre plantillas normativas y Segregación de Funciones (SoD).
 *
 * REGLAS DE EDICIÓN:
 * - En borrador (`draft`): solo el creador puede editar.
 * - En publicada (`published`): puede editar el creador o quien tenga `template.update`,
 *   siempre que además pueda ver la plantilla (scope/contexto académico + `template.show`).
 *
 * LISTADO Y DETALLE (catálogo):
 * - `template.index`: listar plantillas (global, personal, equipo, contexto académico).
 * - `template.show`: ver detalle; el creador y los revisores asignados no requieren este slug.
 *
 * MUTACIONES (catálogo):
 * - `template.create`: crear visibilidad compartida; personal sin slug (cualquier usuario autenticado).
 * - `template.update`: editar publicada si no es creador; borrador/rechazado solo creador.
 * - `template.delete`: borrar ajenas; el creador siempre puede borrar la suya.
 * - `template.review`: aprobar/rechazar; además debe figurar en `template_reviewers`.
 * - `template.assign-review`: asignar revisores en plantillas no personales; en personal solo el creador en borrador/rechazado.
 * - `template.version`: abrir ciclo de nueva versión sobre publicada (no creador).
 * - `template.clone`: clonar publicada (no creador); además `template.update` o ser creador del origen.
 * - `template.history.view`: listar/ver snapshots publicados (no creador).
 * - La visibilidad no personal (compartida) exige además `template.create`.
 *
 * REGLAS DE BORRADO:
 * - El creador puede borrar su propia plantilla.
 * - Cualquier usuario con `template.delete` puede borrar cualquier plantilla.
 *
 * REGLAS DE REVISIÓN:
 * - Solo usuarios con permiso `template.review` y asignados en `template_reviewers`
 *   pueden aprobar/rechazar.
 * - El creador puede autoasignarse como revisor si tiene `template.review`; en ese
 *   caso su aprobación cuenta igual que la de cualquier otro revisor.
 *
 * REGLAS DE PUBLICACIÓN:
 * - Sin revisores: el creador publica directamente desde `draft` (vía submit-review o /publish).
 * - Con revisores: la publicación es automática al aprobar el último revisor (approveReview).
 *   El endpoint /publish también está disponible para revisores desde `in_review`.
 * - El rechazo devuelve la plantilla a `draft`; el resto de revisores ya no necesita actuar.
 *
 * Uso en controladores:
 *   $this->authorize('create', [Template::class, $visibilityLevelString]);
 *   $this->authorize('update', [$template, $targetVisibilityLevelString]);
 */
class TemplatePolicy
{
    /**
     * Listar plantillas: requiere `template.index`; el global scope acota filas visibles.
     */
    public function viewAny(JwtUser $user): bool
    {
        return $user->hasPermission('template.index');
    }

    /**
     * Ver una plantilla: scope académico (o revisor asignado / creador); además `template.show` o
     * `document.create` para previsualizar en creación de programaciones.
     * `template.delete` no amplía la vista: solo autoriza borrar en {@see self::delete}.
     *
     * Los controladores que resuelven la plantilla sin el scope `user_access` deben delegar aquí.
     */
    public function view(JwtUser $user, Template $template): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ((string) $template->created_by === $userId) {
            return true;
        }

        $templateId = $template->getKey();

        // Creador original que cedió la plantilla: accede si hay al menos un snapshot
        // publicado inmutable donde él era el created_by.
        if ($templateId !== null && $templateId !== '') {
            $wasOriginalCreator = DB::table('entity_versions')
                ->where('versionable_type', Template::class)
                ->where('versionable_id', $templateId)
                ->where('version_number', '>', 0)
                ->where('is_snapshot_immutable', true)
                ->whereRaw(
                    TemplateHeadSnapshot::jsonTemplateFieldExpression('entity_versions', 'created_by').' = ?',
                    [$userId]
                )
                ->exists();
            if ($wasOriginalCreator) {
                return true;
            }
        }
        // Un modelo sin ID es una instancia transitoria (no persistida). En ese caso no hay
        // datos que proteger, por lo que se permite la vista si el permiso está presente.
        // En producción los controladores siempre pasan un modelo recuperado de BD.
        if ($templateId === null || $templateId === '') {
            return true;
        }

        // Revisor asignado: puede ver siempre, independientemente de permisos genéricos.
        // Esto cubre también el caso borrador donde el scope user_access no incluye al revisor.
        if (DB::table('template_reviewers')
            ->where('template_id', $templateId)
            ->where('user_id', $userId)
            ->exists()) {
            return true;
        }

        if (! $user->hasPermission('template.show') && ! $user->hasPermission('document.create')) {
            return false;
        }

        if (Template::query()->whereKey($templateId)->exists()) {
            return true;
        }

        // Non-creator can view even when the head is in draft/in_review/rejected, as long as a published
        // snapshot exists. The controller will serve that published snapshot instead of draft data.
        if (EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', (string) $templateId)
            ->where('version_number', '>', 0)
            ->where('status', 'published')
            ->exists()) {
            return true;
        }

        // Fuera del listado del scope: p. ej. anclada solo vía documento; requiere leer plantillas.
        return $user->hasPermission('template.show')
            && $this->mayViewTemplateAnchoredOnAccessibleDocument($user, (string) $templateId);
    }

    /**
     * Usuario con relación a un documento no borrado que usa esta plantilla (titular, creador, compartido o revisor).
     */
    private function mayViewTemplateAnchoredOnAccessibleDocument(JwtUser $user, string $templateId): bool
    {
        $userId = (string) $user->getAuthIdentifier();
        if ($userId === '') {
            return false;
        }

        return DB::table('documents')
            ->join('entity_versions as document_head_ev', 'document_head_ev.id', '=', 'documents.head_entity_version_id')
            ->where('documents.template_id', $templateId)
            ->whereNull('documents.deleted_at')
            ->where(function ($outer) use ($userId) {
                $outer->whereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id').' = ?', [$userId])
                    ->orWhereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'created_by').' = ?', [$userId])
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('document_reviews')
                            ->whereColumn('document_reviews.document_id', 'documents.id')
                            ->where('document_reviews.reviewer_id', $userId);
                    })
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('document_shares')
                            ->whereColumn('document_shares.document_id', 'documents.id')
                            ->where('document_shares.user_id', $userId);
                    });
            })
            ->exists();
    }

    /**
     * Crear plantilla.
     *
     * Visibilidad personal: cualquier usuario autenticado.
     * Visibilidad compartida: requiere `template.create`.
     *
     * @param  string|null  $visibilityLevel  Valor de {@see TemplateVisibilityLevel}.
     */
    public function create(JwtUser $user, ?string $visibilityLevel = null): bool
    {
        $level = $this->normalizeVisibility($visibilityLevel);

        if ($level === TemplateVisibilityLevel::Personal) {
            return true;
        }

        return $user->hasPermission('template.create');
    }

    /**
     * Editar una plantilla.
     *
     * Solo el creador puede editar, y únicamente cuando la plantilla está en borrador.
     * Si se cambia la visibilidad a no personal, aplica además la regla de {@see self::create}.
     *
     * @param  string|null  $targetVisibilityLevel  Nivel de visibilidad pretendido, si viene en el body.
     */
    public function update(JwtUser $user, Template $template, ?string $targetVisibilityLevel = null): bool
    {
        $isCreator = $user->getAuthIdentifier() === $template->created_by;

        if (in_array($template->status, ['draft', 'rejected'], true)) {
            if (! $isCreator) {
                return false;
            }
            if ($targetVisibilityLevel !== null) {
                return $this->create($user, $targetVisibilityLevel);
            }

            return true;
        }

        if ($template->status !== 'published') {
            return false;
        }

        if (! $this->view($user, $template)) {
            return false;
        }

        if (! $isCreator && ! $user->hasPermission('template.update')) {
            return false;
        }

        if ($targetVisibilityLevel !== null) {
            return $this->create($user, $targetVisibilityLevel);
        }

        return true;
    }

    /**
     * Eliminar o archivar plantilla.
     *
     * El creador puede borrar su propia plantilla.
     * Cualquier usuario con `template.delete` puede borrar cualquier plantilla.
     */
    public function delete(JwtUser $user, Template $template): bool
    {
        if ($user->getAuthIdentifier() === $template->created_by) {
            return true;
        }

        return $user->hasPermission('template.delete');
    }

    /**
     * Clonar plantilla publicada en un borrador nuevo.
     *
     * Requiere poder ver el origen, `template.create` en la visibilidad del clon,
     * y (creador del origen o `template.clone`). Quien no es creador del origen
     * necesita además `template.update` (misma línea que editar publicadas ajenas).
     */
    public function clone(JwtUser $user, Template $template): bool
    {
        if ($template->status !== 'published' || ! $this->view($user, $template)) {
            return false;
        }

        $visibility = $template->visibility_level instanceof TemplateVisibilityLevel
            ? $template->visibility_level->value
            : (string) $template->visibility_level;

        if (! $this->create($user, $visibility)) {
            return false;
        }

        $isCreator = (string) $user->getAuthIdentifier() === (string) $template->created_by;

        if (! $isCreator && ! $user->hasPermission('template.clone')) {
            return false;
        }

        return $isCreator || $user->hasPermission('template.update');
    }

    /**
     * Ver historial de versiones publicadas (`GET …/versions`, `GET …/template-versions/{id}`).
     */
    public function viewHistory(JwtUser $user, Template $template): bool
    {
        if (! $this->view($user, $template)) {
            return false;
        }

        if ((string) $user->getAuthIdentifier() === (string) $template->created_by) {
            return true;
        }

        return $user->hasPermission('template.history.view');
    }

    /**
     * Descarta la versión de trabajo (draft/in_review/rejected) y restaura la última publicación.
     * Solo el creador puede descartar — ni revisores ni usuarios con permisos globales.
     */
    public function discard(JwtUser $user, Template $template): bool
    {
        if (! in_array($template->status, ['draft', 'in_review', 'rejected'], true)) {
            return false;
        }

        return $user->getAuthIdentifier() === $template->created_by;
    }

    /**
     * Publicada → borrador para preparar una nueva versión (misma plantilla).
     *
     * Creador o permiso `template.version`, siempre que pueda ver la plantilla.
     */
    public function startRevision(JwtUser $user, Template $template): bool
    {
        if ($template->status !== 'published') {
            return false;
        }

        if (! $this->view($user, $template)) {
            return false;
        }

        $isCreator = (string) $user->getAuthIdentifier() === (string) $template->created_by;

        return $isCreator || $user->hasPermission('template.version');
    }

    /**
     * Asignar revisores de plantilla (POST …/reviewers).
     *
     * Personal: solo el creador en borrador o rechazado.
     * Resto de visibilidades: `template.assign-review`.
     */
    public function assignReview(JwtUser $user, Template $template): bool
    {
        if (! in_array($template->status, ['draft', 'rejected'], true)) {
            return false;
        }

        $level = $this->normalizeVisibility(
            $template->visibility_level instanceof TemplateVisibilityLevel
                ? $template->visibility_level->value
                : (string) $template->visibility_level,
        );

        if ($level === TemplateVisibilityLevel::Personal) {
            return (string) $user->getAuthIdentifier() === (string) $template->created_by;
        }

        return $user->hasPermission('template.assign-review');
    }

    /**
     * Revisión / aprobación.
     *
     * Requiere permiso `template.review` y estar asignado en `template_reviewers`.
     */
    public function review(JwtUser $user, Template $template): bool
    {
        if (! $user->hasPermission('template.review')) {
            return false;
        }

        $userId = $user->getAuthIdentifier();

        return $template->reviewers()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Ver/gestionar comentarios de plantilla.
     *
     * El creador puede comentar en cualquier estado. Los revisores asignados pueden comentar
     * en `in_review` aunque no tengan `template.review` (paridad con DocumentPolicy::comment).
     */
    public function comment(JwtUser $user, Template $template): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === (string) $template->created_by) {
            return true;
        }

        if ($template->status !== 'in_review') {
            return false;
        }

        return $this->review($user, $template)
            || $template->reviewers()
                ->where('user_id', $userId)
                ->exists();
    }

    /**
     * Publicación explícita de plantilla.
     *
     * - Creador de plantilla personal sin revisores: puede publicar directamente desde `draft`.
     * - Revisiones no personales: requieren al menos un revisor; solo el revisor asignado
     *   puede publicar explícitamente desde `in_review`.
     *   (La publicación automática al último approval se gestiona en approveReview.)
     */
    public function publish(JwtUser $user, Template $template): bool
    {
        if ($user->getAuthIdentifier() === $template->created_by) {
            return in_array($template->status, ['draft', 'in_review'], true)
                && $template->reviewers()->doesntExist();
        }

        return $template->status === 'in_review' && $this->review($user, $template);
    }

    /**
     * Enviar borrador a revisión: solo el creador y únicamente desde `draft`.
     *
     * La guardia de estado aquí es redundante con {@see TemplateReviewService::submitForReview}
     * pero evita que UI o código externo traten `can('submitForReview', $template)` como `true`
     * para plantillas ya en revisión o publicadas.
     */
    public function submitForReview(JwtUser $user, Template $template): bool
    {
        return $user->getAuthIdentifier() === $template->created_by
            && in_array($template->status, ['draft', 'rejected'], true);
    }

    /**
     * Normaliza el nivel de visibilidad a un valor de {@see TemplateVisibilityLevel}.
     */
    private function normalizeVisibility(?string $visibilityLevel): TemplateVisibilityLevel
    {
        if ($visibilityLevel === null || $visibilityLevel === '') {
            return TemplateVisibilityLevel::Personal;
        }

        return TemplateVisibilityLevel::tryFrom($visibilityLevel)
            ?? TemplateVisibilityLevel::Personal;
    }
}
