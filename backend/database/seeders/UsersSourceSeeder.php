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
            // Detectar columnas presentes para soportar tanto el stub mínimo
            // (id, name, email) como el extendido (con first_name/last_name/username).
            $hasFirstName = Schema::hasColumn('users', 'first_name');
            $hasLastName  = Schema::hasColumn('users', 'last_name');
            $hasUsername  = Schema::hasColumn('users', 'username');

            DB::table('users')->insertOrIgnore(array_map(
                static function (array $user) use ($now, $hasFirstName, $hasLastName, $hasUsername): array {
                    $row = [
                        'id'         => $user['id'],
                        'name'       => $user['nombre'],
                        'email'      => $user['email'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($hasFirstName) {
                        $row['first_name'] = $user['first_name'] ?? $user['nombre'];
                    }
                    if ($hasLastName) {
                        $row['last_name'] = $user['last_name'] ?? '';
                    }
                    if ($hasUsername) {
                        $row['username'] = $user['username']
                            ?? strtolower(str_replace([' ', '@maya.local'], ['.', ''], (string) $user['email']));
                    }

                    return $row;
                },
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
