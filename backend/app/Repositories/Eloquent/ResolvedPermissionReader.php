<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\ResolvedPermissionReaderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura de permisos resueltos del usuario sobre la vista
 * `user_resolved_permissions` (FDW → `v_dms_user_permissions` en
 * maya_authorization). Reemplaza al antiguo `UserPermissionRepository`
 * que apuntaba a `user_permissions` local en dms.
 *
 * Se mantiene el patrón de caché (15 min) y la cláusula defensiva sobre
 * vistas en PostgreSQL (Schema::hasTable solo ve tablas base).
 */
class ResolvedPermissionReader implements ResolvedPermissionReaderInterface
{
    private const CACHE_PREFIX = 'user_permission_slugs:';

    private const CACHE_TTL_SECONDS = 900;

    private const VIEW_NAME = 'user_resolved_permissions';

    /**
     * @return list<string>
     */
    public function findPermissionSlugsByUserId(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX.$userId;

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        // No persistir listas vacías: si la primera petición fue antes del
        // seed/sync, no queremos bloquear permisos reales hasta expirar.
        if (is_array($cached) && $cached === []) {
            Cache::forget($cacheKey);
        }

        if (! $this->catalogIsReadable()) {
            return [];
        }

        $slugs = DB::table(self::VIEW_NAME)
            ->where('user_id', '=', $userId)
            ->orderBy('permission_slug')
            ->pluck('permission_slug')
            ->unique()
            ->values()
            ->all();

        if ($slugs !== []) {
            Cache::put($cacheKey, $slugs, self::CACHE_TTL_SECONDS);
        }

        return $slugs;
    }

    public function forgetCachedSlugsForUser(string $userId): void
    {
        Cache::forget(self::CACHE_PREFIX.$userId);
    }

    /**
     * En local/producción `user_resolved_permissions` es una vista PostgreSQL
     * sobre FDW; {@see Schema::hasTable()} solo contempla tablas base y
     * devuelve false, lo que haría que nunca se consultaran filas.
     */
    private function catalogIsReadable(): bool
    {
        if (Schema::hasTable(self::VIEW_NAME)) {
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
            [$schema, self::VIEW_NAME],
        ) !== null;
    }
}
