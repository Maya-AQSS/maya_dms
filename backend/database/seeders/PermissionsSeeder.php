<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissions = $this->mockPermissions();

        if ($permissions === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            return [
                'code'        => $row['code'],
                'name'        => $row['name'] ?? null,
                'description' => $row['description'] ?? null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }, $permissions);

        DB::table('permissions')->insertOrIgnore($rows);
    }

    /**
     * @return list<array{code: string, name?: string|null, description?: string|null}>
     */
    private function mockPermissions(): array
    {
        $filePath = database_path('data/permissions_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
