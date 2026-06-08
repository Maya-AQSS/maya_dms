<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para eliminar la columna `assets` de la tabla `themes`.
 *
 * Refactorización: Los assets fijos (logo, background_image, watermark) se han
 * reemplazado con bloques de imagen auto-contenidos dentro de `layout.regions[]`.
 * Esta migración:
 * 1. Intenta convertir datos legacy de `assets` a bloques de layout (best-effort)
 * 2. Elimina la columna `assets`
 *
 * Down: Restaura la columna assets como JSON nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intento best-effort de migración: convertir assets legacy a bloques de layout.
        // Si un theme tiene assets (logo/background_image/watermark), creamos bloques
        // de tipo 'image' en el layout para preservar los datos.
        if (Schema::hasTable('themes')) {
            DB::table('themes')
                ->whereNotNull('assets')
                ->cursor()
                ->each(function ($theme) {
                    $assets = is_array($theme->assets) ? $theme->assets : (json_decode($theme->assets, true) ?? []);
                    $layout = is_array($theme->layout) ? $theme->layout : (json_decode($theme->layout, true) ?? ['regions' => []]);

                    if (empty($layout['regions'])) {
                        $layout['regions'] = [];
                    }

                    // Convertir assets legacy a bloques de imagen
                    $imageBlocks = [];

                    // Logo: crear bloque de imagen en esquina superior izquierda
                    if (! empty($assets['logo_path'])) {
                        $imageBlocks[] = [
                            'type' => 'image',
                            'props' => [
                                'src' => $assets['logo_path'],
                                'alt' => 'Logo',
                                'opacity' => 1.0,
                                'rotate' => 0,
                                'objectFit' => 'contain',
                            ],
                            'layout' => [
                                'x' => 0,
                                'y' => 0,
                                'w' => 100,
                                'h' => 100,
                                'z' => 10,
                            ],
                        ];
                    }

                    // Background: crear bloque en fondo con objectFit cover
                    if (! empty($assets['background_image_path'])) {
                        $imageBlocks[] = [
                            'type' => 'image',
                            'props' => [
                                'src' => $assets['background_image_path'],
                                'alt' => 'Background',
                                'opacity' => 1.0,
                                'rotate' => 0,
                                'objectFit' => 'cover',
                            ],
                            'layout' => [
                                'x' => 0,
                                'y' => 0,
                                'w' => 100,
                                'h' => 100,
                                'z' => 0, // z bajo para estar detrás
                            ],
                        ];
                    }

                    // Watermark: crear bloque semitransparente
                    if (! empty($assets['watermark_path'])) {
                        $imageBlocks[] = [
                            'type' => 'image',
                            'props' => [
                                'src' => $assets['watermark_path'],
                                'alt' => 'Watermark',
                                'opacity' => 0.3,
                                'rotate' => 0,
                                'objectFit' => 'contain',
                            ],
                            'layout' => [
                                'x' => 0,
                                'y' => 0,
                                'w' => 100,
                                'h' => 100,
                                'z' => 5, // En medio
                            ],
                        ];
                    }

                    // Añadir bloques convertidos al layout sin sobrescribir regiones existentes
                    if (! empty($imageBlocks)) {
                        $layout['regions'] = array_merge($layout['regions'] ?? [], $imageBlocks);

                        DB::table('themes')
                            ->where('id', $theme->id)
                            ->update(['layout' => json_encode($layout)]);
                    }
                });
        }

        // Eliminar la columna assets
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn('assets');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->json('assets')->nullable()->after('layout');
        });
    }
};
