<?php

declare(strict_types=1);

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
            $row['process_parent_id'] ??= null;
            $row['description'] ??= null;
            $row['icon'] ??= null;
            $row['color'] ??= null;
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $data);

        // Insertamos primero los procesos top-level (process_parent_id = null) para
        // que la FK no falle al upsertar los subprocesos.
        $top = array_values(array_filter($rows, static fn (array $r): bool => $r['process_parent_id'] === null));
        $subs = array_values(array_filter($rows, static fn (array $r): bool => $r['process_parent_id'] !== null));

        $updateCols = ['code', 'name', 'alias', 'icon', 'color', 'description', 'process_parent_id', 'updated_at'];

        if ($top !== []) {
            DB::table('processes')->upsert($top, ['id'], $updateCols);
        }

        if ($subs !== []) {
            DB::table('processes')->upsert($subs, ['id'], $updateCols);
        }
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
