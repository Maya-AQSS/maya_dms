<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait AssignsTestUserPermissions
{
    /**
     * @param  list<string>  $codes
     */
    protected function assignUserPermissions(string $userId, array $codes): void
    {
        if ($codes === []) {
            return;
        }

        $now = now();
        foreach ($codes as $code) {
            DB::table('user_permissions')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'permission_code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
