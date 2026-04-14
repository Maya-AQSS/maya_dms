<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        $rows = $this->mockRows();
        if ($rows === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;
            $row['deleted_at'] ??= null;

            return $row;
        }, $rows);

        DB::table('documents')->insertOrIgnore($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockRows(): array
    {
        $filePath = database_path('data/documents_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $rows = require $filePath;

        return is_array($rows) ? $rows : [];
    }
}
