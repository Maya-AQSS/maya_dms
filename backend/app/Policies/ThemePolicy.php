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
     * Crear themes: autenticación suficiente. Si se quiere restringir a
     * roles específicos (editor/admin), añadir comprobación de permiso.
     */
    public function create(JwtUser $user): bool
    {
        return true;
    }

    /**
     * Editar: el creador siempre puede; admins de equipo si team_id presente.
     * Por ahora: solo creador (MVP). Roles vendrán después.
     */
    public function update(JwtUser $user, Theme $theme): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $theme->created_by;
    }

    public function delete(JwtUser $user, Theme $theme): bool
    {
        return $this->update($user, $theme);
    }

    /**
     * Cualquier usuario con permiso de crear puede clonar (el clon es nuevo
     * y queda bajo su propiedad).
     */
    public function clone(JwtUser $user, Theme $theme): bool
    {
        return $this->create($user);
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
