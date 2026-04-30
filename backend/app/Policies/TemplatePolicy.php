<?php

namespace App\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\JwtUser;
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autorización sobre plantillas normativas y Segregación de Funciones (SoD).
 *
 * REGLAS DE EDICIÓN:
 * - Solo el creador puede editar una plantilla, y únicamente cuando está en borrador (`draft`).
 * - La visibilidad no personal (compartida) exige además `templates.create`.
 *
 * REGLAS DE BORRADO:
 * - El creador puede borrar su propia plantilla.
 * - Cualquier usuario con `templates.delete` puede borrar cualquier plantilla.
 *
 * REGLAS DE REVISIÓN:
 * - Solo usuarios con permiso `templates.review` y asignados en `template_reviewers`
 *   pueden aprobar/rechazar.
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
     * Ver una plantilla: requiere `templates.read` y visibilidad de catálogo o vínculo con un documento
     * que el usuario ya puede ver (misma idea que el scope de {@see \App\Models\Document}).
     *
     * Los controladores que resuelven la plantilla sin el scope `user_access` deben delegar aquí.
     */
    public function view(JwtUser $user, Template $template): bool
    {
        if (! $user->hasPermission('templates.read')) {
            return false;
        }

        $templateId = $template->getKey();
        if ($templateId === null || $templateId === '') {
            return true;
        }

        if (Template::query()->whereKey($templateId)->exists()) {
            return true;
        }

        return $this->mayViewTemplateAnchoredOnAccessibleDocument($user, (string) $templateId);
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
            ->where('template_id', $templateId)
            ->whereNull('deleted_at')
            ->where(function ($outer) use ($userId) {
                $outer->where('owner_id', $userId)
                    ->orWhere('created_by', $userId)
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
        if ($user->getAuthIdentifier() !== $template->created_by) {
            return false;
        }

        if ($template->status !== 'draft') {
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
     * Solo el creador o un revisor asignado pueden interactuar con comentarios.
     */
    public function comment(JwtUser $user, Template $template): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === $template->created_by) {
            return true;
        }

        if ($this->hasEditShare($template, $userId)) {
            return true;
        }

        return $this->review($user, $template);
    }

    /**
     * Punto de extensión para compartición de plantillas.
     *
     * Si existe soporte de `template_shares`, se permite comentar con permiso `edit`.
     */
    private function hasEditShare(Template $template, string $userId): bool
    {
        if ($userId === '' || ! Schema::hasTable('template_shares')) {
            return false;
        }

        return DB::table('template_shares')
            ->where('template_id', $template->getKey())
            ->where('user_id', $userId)
            ->where('permission', 'edit')
            ->exists();
    }

    /**
     * Publicación de plantilla.
     * 
     * El creador puede publicar directamente si no hay revisores asignados.
     * En caso contrario, solo un revisor asignado puede realizar la publicación.
     */
    public function publish(JwtUser $user, Template $template): bool
    {
        if ($user->getAuthIdentifier() === $template->created_by) {
            return $template->reviewers()->doesntExist();
        }

        return $this->review($user, $template);
    }

    /**
     * Enviar borrador a revisión: solo el creador.
     */
    public function submitForReview(JwtUser $user, Template $template): bool
    {
        return $user->getAuthIdentifier() === $template->created_by;
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
