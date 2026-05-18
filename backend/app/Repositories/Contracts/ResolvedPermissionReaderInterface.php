<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface ResolvedPermissionReaderInterface
{
    /**
     * Slugs de permiso resueltos para el usuario (vista `user_resolved_permissions`).
     *
     * Lee la vista FDW federada con maya_authorization
     * (`v_dms_user_permissions`), que expande la jerarquía de roles + overrides
     * con la columna `permission_slug`.
     *
     * @return list<string>
     */
    public function findPermissionSlugsByUserId(string $userId): array;

    /**
     * Invalida la caché de slugs de permiso para un usuario.
     */
    public function forgetCachedSlugsForUser(string $userId): void;
}
