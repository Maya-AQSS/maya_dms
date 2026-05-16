<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UserPermissionRepositoryInterface
{
    /**
     * Códigos de permiso CRUD asignados al usuario (tabla/vista `user_permissions`).
     *
     * @return list<string>
     */
    public function findPermissionCodesByUserId(string $userId): array;

    /**
     * Invalida la caché de códigos de permiso para un usuario.
     */
    public function forgetCachedCodesForUser(string $userId): void;
}
