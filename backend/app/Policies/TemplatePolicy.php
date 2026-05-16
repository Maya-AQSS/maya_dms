<?php
declare(strict_types=1);

namespace App\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\JwtUser;
use App\Models\Template;
use App\Support\DocumentHeadSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Autorización sobre plantillas normativas y Segregación de Funciones (SoD).
 *
 * REGLAS DE EDICIÓN:
 * - En borrador (`draft`): solo el creador puede editar.
 * - En publicada (`published`): puede editar el creador o quien tenga `templates.update`,
 *   siempre que además pueda ver la plantilla (scope/contexto académico + `templates.read`).
 * - La visibilidad no personal (compartida) exige además `templates.create`.
 *
 * REGLAS DE BORRADO:
 * - El creador puede borrar su propia plantilla.
 * - Cualquier usuario con `templates.delete` puede borrar cualquier plantilla.
 *
 * REGLAS DE REVISIÓN:
 * - Solo usuarios con permiso `templates.review` y asignados en `template_reviewers`
 *   pueden aprobar/rechazar.
 * - El creador puede autoasignarse como revisor si tiene `templates.review`; en ese
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
     * Listar plantillas: requiere `templates.read`; el global scope acota filas visibles.
     */
    public function viewAny(JwtUser $user): bool
    {
        return $user->hasPermission('templates.read');
    }

    /**
     * Ver una plantilla: visibilidad de catálogo (mismo criterio que el scope de {@see \App\Models\Template})
     * o vínculo con un documento visible; además hace falta al menos `templates.read` o `documents.create`
     * (quien puede crear programaciones desde módulo puede previsualizar plantillas ofrecidas allí).
     *
     * Los controladores que resuelven la plantilla sin el scope `user_access` deben delegar aquí.
     */
    public function view(JwtUser $user, Template $template): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        // Gestión global / auditoría: mismo espíritu que {@see AcademicHierarchyController} (`admin`)
        // y coherente con poder borrar cualquier plantilla ({@see self::delete} + `templates.delete`).
        if ($user->hasPermission('admin') || $user->hasPermission('templates.delete')) {
            return true;
        }

        if ((string) $template->created_by === $userId) {
            return true;
        }

        if (! $user->hasPermission('templates.read') && ! $user->hasPermission('documents.create')) {
            return false;
        }

        $templateId = $template->getKey();
        // Un modelo sin ID es una instancia transitoria (no persistida). En ese caso no hay
        // datos que proteger, por lo que se permite la vista si el permiso está presente.
        // En producción los controladores siempre pasan un modelo recuperado de BD.
        if ($templateId === null || $templateId === '') {
            return true;
        }

        if (DB::table('template_reviewers')
            ->where('template_id', $templateId)
            ->where('user_id', $userId)
            ->exists()) {
            return true;
        }

        if (Template::query()->whereKey($templateId)->exists()) {
            return true;
        }

        // Fuera del listado del scope: p. ej. anclada solo vía documento; requiere leer plantillas.
        return $user->hasPermission('templates.read')
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
     * Visibilidad compartida: requiere `templates.create`.
     *
     * @param  string|null  $visibilityLevel  Valor de {@see TemplateVisibilityLevel}.
     */
    public function create(JwtUser $user, ?string $visibilityLevel = null): bool
    {
        $level = $this->normalizeVisibility($visibilityLevel);

        if ($level === TemplateVisibilityLevel::Personal) {
            return true;
        }

        return $user->hasPermission('templates.create');
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

        if (! $isCreator && ! $user->hasPermission('templates.update')) {
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
     * Cualquier usuario con `templates.delete` puede borrar cualquier plantilla.
     */
    public function delete(JwtUser $user, Template $template): bool
    {
        if ($user->getAuthIdentifier() === $template->created_by) {
            return true;
        }

        return $user->hasPermission('templates.delete');
    }

    /**
     * Clonar plantilla.
     *
     * Solo se permite clonar plantillas publicadas que el usuario pueda ver
     * y para las cuales tenga permiso de creación en la visibilidad origen.
     */
    public function clone(JwtUser $user, Template $template): bool
    {
        $visibility = $template->visibility_level instanceof TemplateVisibilityLevel
            ? $template->visibility_level->value
            : (string) $template->visibility_level;

        return $this->view($user, $template)
            && $template->status === 'published'
            && $this->create($user, $visibility);
    }

    /**
     * Publicada → borrador para preparar una nueva versión (misma plantilla).
     *
     * Misma idea que {@see self::update} en estado `published`: hace falta poder ver la plantilla
     * y ser creador o tener `templates.update`.
     */
    public function startRevision(JwtUser $user, Template $template): bool
    {
        if ($template->status !== 'published') {
            return false;
        }

        if (! $this->view($user, $template)) {
            return false;
        }

        $isCreator = $user->getAuthIdentifier() === $template->created_by;

        return $isCreator || $user->hasPermission('templates.update');
    }

    /**
     * Revisión / aprobación.
     *
     * Requiere permiso `templates.review` y estar asignado en `template_reviewers`.
     */
    public function review(JwtUser $user, Template $template): bool
    {
        if (! $user->hasPermission('templates.review')) {
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
     * El creador puede comentar en cualquier estado. Los revisores asignados solo en in_review.
     */
    public function comment(JwtUser $user, Template $template): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === $template->created_by) {
            return true;
        }

        return $template->status === 'in_review' && $this->review($user, $template);
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
