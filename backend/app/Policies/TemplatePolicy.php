<?php

namespace App\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\JwtUser;
use App\Models\Template;

/**
 * Autorización sobre plantillas normativas y segregación de funciones (SoD).
 *
 * Listado y detalle confían en el global scope de {@see Template} (como {@see Team}).
 *
 * Visibilidad compartida (no personal) en alta o cambio de nivel:
 * usar {@see JwtUser::canManageSharedTemplateVisibility()} vía {@see self::create}.
 *
 * En controladores:
 *   $this->authorize('create', [Template::class, $visibilityLevelString]);
 *   $this->authorize('update', [$template, $targetVisibilityLevelString]); // opcional 2.º arg si se envía visibility_level
 */
class TemplatePolicy
{
    /**
     * Listar plantillas (el alcance lo acota el modelo / repositorio).
     */
    public function viewAny(JwtUser $user): bool
    {
        return true;
    }

    /**
     * Ver una plantilla (el alcance impide cargar filas no visibles).
     */
    public function view(JwtUser $user, Template $template): bool
    {
        return true;
    }

    /**
     * Crear plantilla. Si el segundo argumento se omite (solo clase), se asume visibilidad personal.
     *
     * @param  string|null  $visibilityLevel  Valor de {@see TemplateVisibilityLevel} (p. ej. tras array_shift del FQCN).
     */
    public function create(JwtUser $user, ?string $visibilityLevel = null): bool
    {
        $level = $this->normalizeVisibility($visibilityLevel);

        if ($level === TemplateVisibilityLevel::Personal) {
            return true;
        }

        return $user->canManageSharedTemplateVisibility();
    }

    /**
     * Actualizar plantilla. Si se pasa el nivel de visibilidad objetivo, aplica la misma regla que en {@see create}.
     *
     * @param  string|null  $targetVisibilityLevel  Valor pretendido de visibility_level en el body, si viene en la petición.
     */
    public function update(JwtUser $user, Template $template, ?string $targetVisibilityLevel = null): bool
    {
        if (! $this->userCanEditTemplate($user, $template)) {
            return false;
        }

        if ($targetVisibilityLevel !== null) {
            return $this->create($user, $targetVisibilityLevel);
        }

        return true;
    }

    /**
     * Eliminar o archivar plantilla.
     */
    public function delete(JwtUser $user, Template $template): bool
    {
        return $this->userCanEditTemplate($user, $template);
    }

    /**
     * Generar copia en borrador; quien puede ver la plantilla puede clonarla.
     */
    public function clone(JwtUser $user, Template $template): bool
    {
        return $this->view($user, $template);
    }

    /**
     * Revisión / aprobación en el flujo de plantilla (SoD: el creador no aprueba la suya).
     */
    public function review(JwtUser $user, Template $template): bool
    {
        return $user->getAuthIdentifier() !== $template->created_by;
    }

    /**
     * Enviar borrador a revisión (autor o quien puede editar la plantilla).
     */
    public function submitForReview(JwtUser $user, Template $template): bool
    {
        return $this->userCanEditTemplate($user, $template);
    }

    /**
     * Volver a borrador una plantilla publicada para preparar una nueva versión.
     */
    public function reopenDraft(JwtUser $user, Template $template): bool
    {
        if ($template->status !== 'published') {
            return false;
        }

        return $this->userCanEditTemplate($user, $template);
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

    /**
     * Verifica si el usuario puede editar la plantilla.
     */
    private function userCanEditTemplate(JwtUser $user, Template $template): bool
    {
        if ($user->getAuthIdentifier() === $template->created_by) {
            return true;
        }

        return $user->canManageSharedTemplateVisibility();
    }
}
