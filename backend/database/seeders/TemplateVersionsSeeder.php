<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TemplateVersionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('template_versions')) {
            return;
        }

        $rows = $this->mockRows();
        if ($rows === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            $row['published_at'] ??= $now;

            $snapshot = $row['blocks_snapshot'] ?? [];
            $row['blocks_snapshot'] = is_string($snapshot)
                ? $snapshot
                : json_encode($snapshot);

            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $rows);

        DB::table('template_versions')->insertOrIgnore($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockRows(): array
    {
        $filePath = database_path('data/template_versions_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $rows = require $filePath;

        return is_array($rows) ? $rows : [];
    }
}
