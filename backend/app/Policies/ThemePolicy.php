<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JwtUser;
use App\Models\Theme;

/**
 * Autorización sobre themes (identidad visual reutilizable).
 *
 * Navegación y catálogo de gestión (/themes, borradores, archivados…):
 * - `theme.index`: listar en cualquier estado (sin `status=published` obligatorio).
 * - `theme.show`: ver detalle en cualquier estado.
 * En el frontend la ruta /themes y el menú exigen `theme.index` y `theme.show`.
 *
 * Selector del wizard de plantilla (paso propiedades), no la sección Themes:
 * - Sin `theme.index` / `theme.show`, con `dms.login`: solo
 *   `GET /themes?status=published`, ver un theme publicado y sus assets (p. ej. profesor).
 *
 * Mutaciones:
 * - `theme.create`: crear themes (jefe de departamento en adelante).
 * - `theme.update`: editar themes ajenos; el creador puede editar el suyo sin este slug.
 * - `theme.clone`: clonar (jefe de estudios en adelante) si puede ver el origen.
 * - `theme.delete`: borrar ajenos (solo admin); el creador siempre puede borrar el suyo.
 */
class ThemePolicy
{
    /**
     * Listar themes (gestión): requiere `theme.index`.
     * El listado publicado para plantillas usa {@see viewPublishedForTemplate}.
     */
    public function viewAny(JwtUser $user): bool
    {
        return $user->hasPermission('theme.index');
    }

    /**
     * Listado acotado para el selector del wizard de plantilla (no autoriza /themes en menú).
     * Solo aplica cuando IndexThemeRequest recibe `status=published`.
     */
    public function viewPublishedForTemplate(JwtUser $user): bool
    {
        return $this->maySelectThemeForTemplate($user);
    }

    /**
     * Ver detalle: `theme.show` (gestión) o theme publicado con `dms.login` (selector en plantilla).
     */
    public function view(JwtUser $user, Theme $theme): bool
    {
        if ($user->hasPermission('theme.show')) {
            return true;
        }

        return $theme->status === 'published' && $this->maySelectThemeForTemplate($user);
    }

    /**
     * Crear themes: requiere `theme.create`.
     */
    public function create(JwtUser $user): bool
    {
        return $user->hasPermission('theme.create');
    }

    /**
     * Editar: el creador siempre puede; el resto necesita `theme.show` (vía {@see view})
     * y `theme.update`.
     */
    public function update(JwtUser $user, Theme $theme): bool
    {
        if (! $this->view($user, $theme)) {
            return false;
        }

        $isCreator = (string) $user->getAuthIdentifier() === (string) $theme->created_by;

        return $isCreator || $user->hasPermission('theme.update');
    }

    /**
     * Eliminar: el creador siempre; cualquier usuario con `theme.delete` (admin).
     */
    public function delete(JwtUser $user, Theme $theme): bool
    {
        if (! $this->view($user, $theme)) {
            return false;
        }

        if ((string) $user->getAuthIdentifier() === (string) $theme->created_by) {
            return true;
        }

        return $user->hasPermission('theme.delete');
    }

    /**
     * Clonar: requiere `theme.clone` y poder ver el origen.
     */
    public function clone(JwtUser $user, Theme $theme): bool
    {
        return $user->hasPermission('theme.clone') && $this->view($user, $theme);
    }

    /**
     * Usuario autenticado en DMS que puede asignar un theme publicado a una plantilla
     * (incluye profesor sin permisos de catálogo de themes).
     */
    private function maySelectThemeForTemplate(JwtUser $user): bool
    {
        return $user->hasPermission('dms.login');
    }
}
