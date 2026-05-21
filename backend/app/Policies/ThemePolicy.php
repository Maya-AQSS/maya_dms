<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JwtUser;
use App\Models\Theme;

class ThemePolicy
{
    /**
     * Cualquier usuario autenticado puede listar themes (son recursos de
     * identidad visual reutilizable, no documentos sensibles).
     */
    public function viewAny(JwtUser $user): bool
    {
        return true;
    }

    /**
     * Ver detalle: todos los autenticados. Si en el futuro se requiere
     * restringir por team_id, ampliar aquí.
     */
    public function view(JwtUser $user, Theme $theme): bool
    {
        return true;
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
}
