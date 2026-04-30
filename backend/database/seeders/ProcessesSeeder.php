<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcessesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('processes')) {
            return;
        }

        $data = $this->mockData()['processes'] ?? [];
        if ($data === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $data);

        DB::table('processes')->upsert(
            $rows,
            ['id'],
            ['code', 'name', 'alias', 'updated_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mockData(): array
    {
        $filePath = database_path('data/processes_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
