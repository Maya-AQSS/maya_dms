<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsersSourceSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->mockUsers();

        $now = Carbon::now();

        if (Schema::hasTable('users_source')) {
            $rows = array_map(static function (array $user) use ($now): array {
                $user['created_at'] ??= $now;
                $user['updated_at'] ??= $now;

                return $user;
            }, $users);

            DB::table('users_source')->insertOrIgnore($rows);
        }

        // En testing puede existir tabla users local en lugar de objetos FDW.
        if (Schema::hasTable('users')) {
            DB::table('users')->insertOrIgnore(array_map(
                static fn (array $user): array => [
                    'id' => $user['id'],
                    'name' => $user['nombre'],
                    'email' => $user['email'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $users
            ));
        }
    }

    /**
     * Lee los datos mock del catálogo desde database/data/users_mock.php.
     */
    private function mockUsers(): array
    {
        $filePath = database_path('data/users_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $users = require $filePath;

        return is_array($users) ? $users : [];
    }
}
