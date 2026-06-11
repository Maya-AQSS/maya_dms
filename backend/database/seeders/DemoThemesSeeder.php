<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temas demo limpios (no de sistema) usados por las plantillas de demostración.
 *
 * A diferencia de {@see DefaultThemeSeeder} (tema de sistema, no borrable), estos
 * son temas normales: editables, clonables y eliminables por un admin. Sirven para
 * que la plantilla de maquetación demuestre el override de tema por bloque sin
 * depender de datos creados en vivo (que arrastran rutas de media e imágenes con
 * tokens del slot, no portables).
 *
 * Derivado y depurado del tema «tema test» capturado en el snapshot del 2026-06-10:
 * misma forma de `layout`/`palette`/`typography`, pero con valores presentables y
 * sin `src` de imágenes rotas.
 *
 * Idempotente: UUID fijo + insertOrIgnore. No pisa ediciones de admin entre reseeds.
 */
class DemoThemesSeeder extends Seeder
{
    /** UUID fijo del tema demo — determinista para referencias por bloque y reseed. */
    public const DEMO_THEME_ID = 'ce000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        if (! Schema::hasTable('themes')) {
            return;
        }

        if (DB::table('themes')->where('id', self::DEMO_THEME_ID)->exists()) {
            return;
        }

        $now = Carbon::now();

        $layout = [
            'regions' => [
                [
                    'id' => 'r-content-slot',
                    'type' => 'content_slot',
                    'box' => ['x' => 20, 'y' => 20, 'w' => 170, 'h' => 250, 'z' => 1],
                    'props' => ['label' => 'Aquí se carga el cuerpo del documento'],
                ],
                [
                    'id' => 'r-page-number',
                    'type' => 'page_number',
                    'box' => ['x' => 155, 'y' => 285, 'w' => 45, 'h' => 8, 'z' => 2],
                    'props' => ['format' => 'page-of-pages', 'align' => 'right'],
                ],
                [
                    'id' => 'r-date',
                    'type' => 'date',
                    'box' => ['x' => 20, 'y' => 285, 'w' => 60, 'h' => 8, 'z' => 2],
                    'props' => ['format' => 'long', 'align' => 'left'],
                ],
            ],
            'page' => [
                'size' => 'A4',
                'margin_cm' => ['top' => 2.5, 'right' => 2, 'bottom' => 2.5, 'left' => 2],
            ],
        ];

        DB::table('themes')->insertOrIgnore([[
            'id' => self::DEMO_THEME_ID,
            'name' => 'Tema CEEDCV — Maquetación demo',
            'description' => 'Tema de demostración con cabecera de página y pie con número y fecha. '
                .'Editable y clonable; pensado para ilustrar el override de tema por bloque.',
            'status' => 'published',
            'created_by' => $this->demoAuthorId(),
            'team_id' => null,
            'palette' => json_encode([
                'primary' => '#1a5fb4',
                'secondary' => '#3584e4',
                'text' => '#1a1a1a',
                'background' => '#ffffff',
                'accent' => '#26a269',
            ], JSON_UNESCAPED_UNICODE),
            'typography' => json_encode([
                'heading_font' => 'Roboto, sans-serif',
                'body_font' => 'Liberation Serif, serif',
                'base_size_pt' => 11,
                'line_height' => 1.4,
            ], JSON_UNESCAPED_UNICODE),
            'layout' => json_encode($layout, JSON_UNESCAPED_UNICODE),
            'accessibility' => json_encode([
                'language' => 'es',
                'title' => null,
                'subject' => null,
                'author' => 'CEEDCV',
            ], JSON_UNESCAPED_UNICODE),
            'is_system' => false,
            'cloned_from_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }

    /**
     * Autor del tema demo. Usa el usuario de dirección dev cuando hay pack FDW;
     * cae a un UUID estable si no está disponible (p. ej. tests sin FDW).
     */
    private function demoAuthorId(): string
    {
        $devUsersFile = database_path('data/maya_dev_users.php');

        if (is_file($devUsersFile)) {
            /** @var array<string, string> $devUsers */
            $devUsers = require $devUsersFile;
            $author = $devUsers['direccion'] ?? $devUsers['superadmin'] ?? null;
            if (is_string($author) && $author !== '') {
                return $author;
            }
        }

        return self::DEMO_THEME_ID;
    }
}
