<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserFavoritesSeeder extends Seeder
{
    public function run(): void
    {
        $data = $this->mockData();
        $now = Carbon::now();

        if (Schema::hasTable('user_favorite_templates')) {
            $templates = $data['favorite_templates'] ?? [];
            if ($templates !== []) {
                $rows = array_map(static function (array $row) use ($now): array {
                    return [
                        'user_id'       => $row['user_id'],
                        'template_id'   => $row['template_id'],
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }, $templates);

                DB::table('user_favorite_templates')->insertOrIgnore($rows);
            }
        }

        if (Schema::hasTable('user_favorite_documents')) {
            $documents = $data['favorite_documents'] ?? [];
            if ($documents !== []) {
                $rows = array_map(static function (array $row) use ($now): array {
                    return [
                        'user_id'      => $row['user_id'],
                        'document_id'  => $row['document_id'],
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }, $documents);

                DB::table('user_favorite_documents')->insertOrIgnore($rows);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mockData(): array
    {
        $filePath = database_path('data/user_favorites_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
