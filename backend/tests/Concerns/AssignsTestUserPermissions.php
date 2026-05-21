<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait AssignsTestUserPermissions
{
    /**
     * Asigna slugs de permiso al usuario en la tabla stub
     * `user_resolved_permissions` (en testing).
     *
     * @param  list<string>  $slugs
     * @param  bool  $withAppLogin  Si true, añade permisos base de app y procesos salvo que ya estén en $slugs.
     */
    protected function assignUserPermissions(string $userId, array $slugs, bool $withAppLogin = true): void
    {
        if ($withAppLogin) {
            foreach (['dms.login', 'dms.index', 'process.index', 'process.show'] as $appSlug) {
                if (! in_array($appSlug, $slugs, true)) {
                    $slugs = [$appSlug, ...$slugs];
                }
            }
        }

        if ($slugs === []) {
            return;
        }

        foreach ($slugs as $slug) {
            DB::table('user_resolved_permissions')->insertOrIgnore([
                'user_id' => $userId,
                'permission_slug' => $slug,
            ]);
        }
    }
}
