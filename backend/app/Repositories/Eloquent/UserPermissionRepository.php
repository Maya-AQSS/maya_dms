<?php

declare(strict_types=1);

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

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        // No persistir listas vacías: si la primera petición fue antes del seed, el antiguo
        // Cache::remember guardaba [] 15 min y bloqueaba permisos reales hasta expirar.
        if (is_array($cached) && $cached === []) {
            Cache::forget($cacheKey);
        }

        if (! $this->userPermissionsCatalogIsReadable()) {
            return [];
        }

        $codes = UserPermission::query()
            ->where('user_id', '=', $userId)
            ->orderBy('permission_code')
            ->pluck('permission_code')
            ->unique()
            ->values()
            ->all();

        if ($codes !== []) {
            Cache::put($cacheKey, $codes, self::CACHE_TTL_SECONDS);
        }

        return $codes;
    }

    /**
     * Invalida la caché de códigos de permiso para un usuario.
     */
    public function forgetCachedCodesForUser(string $userId): void
    {
        Cache::forget(self::CACHE_PREFIX.$userId);
    }

    /**
     * En local/producción `user_permissions` es una vista PostgreSQL sobre FDW;
     * {@see Schema::hasTable()} solo contempla tablas base y devuelve false, lo que
     * hacía que nunca se consultaran filas (permisos siempre vacíos).
     */
    private function userPermissionsCatalogIsReadable(): bool
    {
        if (Schema::hasTable('user_permissions')) {
            return true;
        }

        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'pgsql') {
            return false;
        }

        $searchPath = (string) ($connection->getConfig('search_path') ?? 'public');
        $schema = trim(explode(',', $searchPath)[0], '" ');

        return $connection->selectOne(
            'select 1 as x from information_schema.views where table_schema = ? and table_name = ? limit 1',
            [$schema, 'user_permissions'],
        ) !== null;
    }
}
