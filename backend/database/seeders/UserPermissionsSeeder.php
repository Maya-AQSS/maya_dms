<?php

namespace Database\Seeders;

use App\Repositories\Contracts\ResolvedPermissionReaderInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed de asignaciones usuario→permiso para el ecosistema dms.
 *
 * En testing, `user_resolved_permissions` es una tabla física stub con
 * columnas `(user_id, permission_slug)` creada por la migración shared
 * `maya/shared-profile-laravel`. En local/staging/prod los permisos viven
 * en maya_authorization (`v_dms_user_permissions`) — este seeder es no-op
 * fuera de testing porque dms no puede escribir en la vista FDW.
 */
class UserPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);

        if (! Schema::hasTable('user_resolved_permissions')) {
            return;
        }

        $assignments = $this->mockAssignments();

        if ($assignments === []) {
            return;
        }

        $rows = array_map(static function (array $row): array {
            return [
                'user_id'         => $row['user_id'],
                'permission_slug' => $row['permission_slug'],
            ];
        }, $assignments);

        DB::table('user_resolved_permissions')->insertOrIgnore($rows);

        $userIds = array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['user_id'],
            $assignments,
        )));

        foreach ($userIds as $userId) {
            app(ResolvedPermissionReaderInterface::class)->forgetCachedSlugsForUser($userId);
            app(UserProfileServiceInterface::class)->invalidateCache($userId);
        }
    }

    /**
     * @return list<array{user_id: string, permission_slug: string}>
     */
    private function mockAssignments(): array
    {
        $filePath = database_path('data/user_permissions_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
