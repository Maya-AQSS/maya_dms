<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentBlocksSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('document_blocks')) {
            return;
        }

        $rows = $this->mockRows();
        if ($rows === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            if (isset($row['content']) && is_array($row['content'])) {
                $row['content'] = json_encode($row['content']);
            }

            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $rows);

        DB::table('document_blocks')->insertOrIgnore($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockRows(): array
    {
        $filePath = database_path('data/document_blocks_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $rows = require $filePath;

        return is_array($rows) ? $rows : [];
    }
}
