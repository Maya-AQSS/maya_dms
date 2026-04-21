<?php

namespace App\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\JwtUser;
use App\Models\Template;

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
 * - Solo los revisores explícitamente asignados en `template_reviewers` pueden aprobar/rechazar.
 * - El creador nunca puede ser revisor de su propia plantilla.
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
     * Ver una plantilla: requiere `templates.read`; el scope impide cargar filas ajenas.
     */
    public function view(JwtUser $user, Template $template): bool
    {
        return $user->hasPermission('templates.read');
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
     * Clonar plantilla: cualquier usuario que pueda verla puede clonarla.
     */
    public function clone(JwtUser $user, Template $template): bool
    {
        return $this->view($user, $template);
    }

    /**
     * Revisión / aprobación (SoD — F-01.3).
     *
     * Requiere estar asignado en `template_reviewers` Y no ser el creador de la plantilla.
     * El creador nunca puede aprobar o rechazar su propia plantilla.
     */
    public function review(JwtUser $user, Template $template): bool
    {
        $userId = $user->getAuthIdentifier();

        if ($userId === $template->created_by) {
            return false;
        }

        return $template->reviewers()
            ->where('user_id', $userId)
            ->exists();
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
