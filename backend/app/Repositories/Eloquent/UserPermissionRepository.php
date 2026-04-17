<?php

namespace App\Repositories\Eloquent;

use App\Models\UserPermission;
use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura de asignaciones usuario ↔ permiso (vista `user_permissions` / FDW o tabla en testing).
 */
class UserPermissionRepository implements UserPermissionRepositoryInterface
{
    private const CACHE_PREFIX = 'user_permission_codes:';

    private const CACHE_TTL_SECONDS = 900;

    /**
     * Códigos de permiso CRUD asignados al usuario (tabla/vista `user_permissions`).
     *
     * @return list<string>
     */
    public function findPermissionCodesByUserId(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX.$userId;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($userId): array {
            if (! Schema::hasTable('user_permissions')) {
                return [];
            }

            return UserPermission::query()
                ->where('user_id', '=', $userId)
                ->orderBy('permission_code')
                ->pluck('permission_code')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Invalida la caché de códigos de permiso para un usuario.
     */
    public function forgetCachedCodesForUser(string $userId): void
    {
        Cache::forget(self::CACHE_PREFIX.$userId);
    }
}
