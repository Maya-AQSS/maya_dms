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
     */
    protected function assignUserPermissions(string $userId, array $slugs): void
    {
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
