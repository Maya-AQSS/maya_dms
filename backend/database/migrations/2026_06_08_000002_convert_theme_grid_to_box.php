<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte la geometría de los bloques de layout de celdas de rejilla (`grid`,
 * 12 columnas × 52 filas) a milímetros absolutos (`box`).
 *
 * Motivación: el editor visual pasa de una rejilla de 12 columnas a un lienzo
 * de posicionamiento absoluto en mm (más preciso y reutilizable). Las cajas en
 * mm son relativas a la esquina superior-izquierda de la página.
 *
 * Conversión (por tamaño de página): colW = anchoMm/12, rowH = altoMm/52.
 *   box.x = grid.x * colW   ·   box.w = grid.w * colW
 *   box.y = grid.y * rowH   ·   box.h = grid.h * rowH   ·   box.z = grid.z
 *
 * Down: reconstruye `grid` (celdas) dividiendo por las mismas constantes.
 */
return new class extends Migration
{
    /** @var array<string, array{w: float, h: float}> */
    private array $pageSizesMm = [
        'A4' => ['w' => 210.0, 'h' => 297.0],
        'Letter' => ['w' => 215.9, 'h' => 279.4],
        'A3' => ['w' => 297.0, 'h' => 420.0],
    ];

    public function up(): void
    {
        $this->remapRegions(function (array $region, float $colW, float $rowH): array {
            if (! isset($region['box']) && isset($region['grid']) && is_array($region['grid'])) {
                $g = $region['grid'];
                $region['box'] = [
                    'x' => round(((float) ($g['x'] ?? 0)) * $colW, 2),
                    'y' => round(((float) ($g['y'] ?? 0)) * $rowH, 2),
                    'w' => round(((float) ($g['w'] ?? 0)) * $colW, 2),
                    'h' => round(((float) ($g['h'] ?? 0)) * $rowH, 2),
                    'z' => (int) ($g['z'] ?? 1),
                ];
            }
            unset($region['grid']);

            return $region;
        });
    }

    public function down(): void
    {
        $this->remapRegions(function (array $region, float $colW, float $rowH): array {
            if (! isset($region['grid']) && isset($region['box']) && is_array($region['box'])) {
                $b = $region['box'];
                $region['grid'] = [
                    'x' => (int) round(((float) ($b['x'] ?? 0)) / $colW),
                    'y' => (int) round(((float) ($b['y'] ?? 0)) / $rowH),
                    'w' => (int) round(((float) ($b['w'] ?? 0)) / $colW),
                    'h' => (int) round(((float) ($b['h'] ?? 0)) / $rowH),
                    'z' => (int) ($b['z'] ?? 1),
                ];
            }
            unset($region['box']);

            return $region;
        });
    }

    /**
     * Recorre cada theme y reescribe sus regions con el callback, usando las
     * constantes de conversión propias del tamaño de página del theme.
     *
     * @param  callable(array<string,mixed>, float, float): array<string,mixed>  $mapRegion
     */
    private function remapRegions(callable $mapRegion): void
    {
        if (! Schema::hasTable('themes')) {
            return;
        }

        DB::table('themes')->whereNotNull('layout')->cursor()->each(function ($theme) use ($mapRegion) {
            $layout = is_array($theme->layout)
                ? $theme->layout
                : (json_decode($theme->layout, true) ?? []);

            if (empty($layout['regions']) || ! is_array($layout['regions'])) {
                return;
            }

            $size = $layout['page']['size'] ?? 'A4';
            $dims = $this->pageSizesMm[$size] ?? $this->pageSizesMm['A4'];
            $colW = $dims['w'] / 12.0;
            $rowH = $dims['h'] / 52.0;

            $layout['regions'] = array_map(
                fn ($region) => is_array($region) ? $mapRegion($region, $colW, $rowH) : $region,
                $layout['regions'],
            );

            DB::table('themes')->where('id', $theme->id)->update(['layout' => json_encode($layout)]);
        });
    }
};
