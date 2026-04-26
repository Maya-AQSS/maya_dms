<?php

namespace Database\Seeders;

use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);

        $table = $this->writableAssignmentsTable();

        if ($table === null) {
            return;
        }

        $assignments = $this->mockAssignments();

        if ($assignments === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            return [
                'id'              => (string) Str::uuid(),
                'user_id'         => $row['user_id'],
                'permission_code' => $row['permission_code'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }, $assignments);

        DB::table($table)->insertOrIgnore($rows);

        $userIds = array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['user_id'],
            $assignments,
        )));

        foreach ($userIds as $userId) {
            app(UserPermissionRepositoryInterface::class)->forgetCachedCodesForUser($userId);
            app(UserProfileServiceInterface::class)->invalidateCache($userId);
        }
    }

    /**
     * Tabla física donde se pueden insertar filas (no la vista FDW de producción).
     */
    private function writableAssignmentsTable(): ?string
    {
        if (Schema::hasTable('user_permissions_source')) {
            return 'user_permissions_source';
        }

        if (app()->environment('testing') && Schema::hasTable('user_permissions')) {
            return 'user_permissions';
        }

        return null;
    }

    /**
     * @return list<array{user_id: string, permission_code: string}>
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
