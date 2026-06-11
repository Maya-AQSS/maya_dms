<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\TemplateBlockDescriptionNormalizer;
use Database\Seeders\Support\SeedContentShape;
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
            if (isset($row['default_content'])) {
                $content = $row['default_content'];

                // Bloques estructurales (portada, índice, hoja en blanco) no llevan
                // cuerpo Tiptap: la portada guarda un payload de maquetación
                // `{"kind":"cover",...}` (geometría/regiones) que NO debe pasar por el
                // normalizador Tiptap (lo aplastaría a un doc vacío). Se serializa tal
                // cual; el resto de bloques (content) sí se normalizan a Tiptap.
                $isLayoutPayload = is_array($content) && isset($content['kind']);
                $row['default_content'] = $isLayoutPayload
                    ? json_encode($content, JSON_UNESCAPED_UNICODE)
                    : SeedContentShape::toTiptapJson($content);
            }

            unset($row['type'], $row['mandatory']);

            if (array_key_exists('description', $row)) {
                // La descripción se almacena como doc Tiptap (igual que default_content),
                // no como texto plano: el modelo la castea/sirve como rich text.
                $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc($row['description']);
                $row['description'] = $doc === null ? null : json_encode($doc, JSON_UNESCAPED_UNICODE);
            }

            // Columnas de maquetación NOT NULL con default en BD: hay que mandar un
            // valor concreto (no NULL) porque la normalización de columnas posterior
            // rellena con NULL las claves ausentes, lo que violaría el NOT NULL.
            $row['block_type'] ??= 'content';
            $row['apply_theme'] = array_key_exists('apply_theme', $row) ? (bool) $row['apply_theme'] : true;
            $row['page_break_after'] = array_key_exists('page_break_after', $row) ? (bool) $row['page_break_after'] : false;
            $row['theme_id'] ??= null;

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
            'block_type',
            'theme_id',
            'apply_theme',
            'title',
            'default_content',
            'description',
            'block_state',
            'page_break_after',
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
