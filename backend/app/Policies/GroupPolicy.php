<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\JwtUser;

class GroupPolicy
{
    /**
     * Listar: cualquier usuario autenticado; el alcance lo filtra {@see Group} global scope.
     */
    public function viewAny(JwtUser $user): bool
    {
        return true;
    }

    /**
     * Ver un grupo: cualquier usuario autenticado; el alcance lo filtra {@see Group} global scope.
     */
    public function view(JwtUser $user, Group $group): bool
    {
        return true;
    }

    /**
     * Crear un grupo: solo usuarios con permisos de gestión.
     */
    public function create(JwtUser $user): bool
    {
        return $this->managesGroups($user);
    }

    /**
     * Actualizar un grupo: solo usuarios con permisos de gestión.
     */
    public function update(JwtUser $user, Group $group): bool
    {
        return $this->managesGroups($user);
    }

    /**
     * Eliminar un grupo: solo usuarios con permisos de gestión.
     */
    public function delete(JwtUser $user, Group $group): bool
    {
        return $this->managesGroups($user);
    }

    /**
     * Alta/baja de miembros en el grupo: solo usuarios con permisos de gestión.
     */
    public function manageMembers(JwtUser $user, Group $group): bool
    {
        return $this->managesGroups($user);
    }

    /**
     * Verifica si el usuario tiene permisos de gestión de grupos.
     */
    private function managesGroups(JwtUser $user): bool
    {
        foreach (config('auth.group_management_roles', []) as $role) {
            if ($user->hasRole((string) $role)) {
                return true;
            }
        }

        return false;
    }
}
