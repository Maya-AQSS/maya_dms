<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsersSourceSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->mockUsers();

        if (Schema::hasTable('users_source')) {
            DB::table('users_source')->insertOrIgnore($users);
        }

        // En testing puede existir tabla users local en lugar de objetos FDW.
        if (Schema::hasTable('users')) {
            DB::table('users')->insertOrIgnore(array_map(
                static fn (array $user): array => [
                    'id' => $user['id'],
                    'name' => $user['nombre'],
                    'email' => $user['email'],
                    'department' => $user['departamento'],
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
