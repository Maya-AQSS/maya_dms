<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TemplateBlocksSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('template_blocks')) {
            return;
        }

        $rows = $this->mockRows();
        if ($rows === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            if (isset($row['default_content']) && is_string($row['default_content'])) {
                $decoded = json_decode($row['default_content'], true);
                $row['default_content'] = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : $row['default_content'];
            } elseif (isset($row['default_content']) && is_array($row['default_content'])) {
                $row['default_content'] = json_encode($row['default_content']);
            }

            unset($row['type'], $row['mandatory']);

            if (array_key_exists('description', $row)) {
                if (is_array($row['description']) || is_object($row['description'])) {
                    $row['description'] = json_encode($row['description'], JSON_UNESCAPED_UNICODE);
                }
            }

            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $rows);

        /*
         * Laravel arma columnas desde la primera fila y un placeholder por clave en cada fila.
         * Si solo algunas filas traen `description`, el INSERT masivo queda descuadrado y PostgreSQL falla.
         */
        $columnOrder = [
            'id',
            'template_id',
            'title',
            'default_content',
            'description',
            'block_state',
            'sort_order',
            'created_at',
            'updated_at',
        ];

        $rows = array_map(static function (array $row) use ($columnOrder): array {
            $normalized = [];
            foreach ($columnOrder as $column) {
                $normalized[$column] = $row[$column] ?? null;
            }

            return $normalized;
        }, $rows);

        DB::table('template_blocks')->insertOrIgnore($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockRows(): array
    {
        $filePath = database_path('data/template_blocks_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $rows = require $filePath;

        return is_array($rows) ? $rows : [];
    }
}
