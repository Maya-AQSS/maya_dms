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
            $row['parent_id']   ??= null;
            $row['description'] ??= null;
            $row['created_at']  ??= $now;
            $row['updated_at']  ??= $now;

            return $row;
        }, $data);

        // Insertamos primero los procesos top-level (parent_id = null) para
        // que la FK no falle al upsertar los subprocesos.
        $top  = array_values(array_filter($rows, static fn (array $r): bool => $r['parent_id'] === null));
        $subs = array_values(array_filter($rows, static fn (array $r): bool => $r['parent_id'] !== null));

        if ($top !== []) {
            DB::table('processes')->upsert(
                $top,
                ['id'],
                ['code', 'name', 'alias', 'description', 'parent_id', 'updated_at'],
            );
        }

        if ($subs !== []) {
            DB::table('processes')->upsert(
                $subs,
                ['id'],
                ['code', 'name', 'alias', 'description', 'parent_id', 'updated_at'],
            );
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
