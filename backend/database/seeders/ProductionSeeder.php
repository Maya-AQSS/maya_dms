<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de referencia de producción (plan de las apps, § 4.5).
 *
 * Se lanza a mano y una vez, tras el primer despliegue, con el Job de
 * deploy/jobs/artisan-job.yaml (nunca en el hook de migración):
 *
 *   php artisan db:seed --class=ProductionSeeder --force
 *
 * - Procesos: el mapa de procesos del CEEDCV (database/data/processes_mock.php, UUID fijos).
 *   Solo se insertan los que faltan: no pisa lo que se edite desde DocuCEED (a diferencia de
 *   ProcessesSeeder, que actualiza) ni resucita los borrados.
 * - Tema por defecto: DefaultThemeSeeder con un autor de sistema, sin los usuarios de
 *   desarrollo de maya_dev_users.php. Si ya existe, no se toca.
 *
 * Nada más: plantillas, documentos y temas de ejemplo son datos de demostración.
 * Idempotente: la segunda vez no cambia nada.
 */
final class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedProcesses();
            $this->resolve(DefaultThemeSeeder::class)->seedTheme(DefaultThemeSeeder::SYSTEM_AUTHOR_ID);
        });

        $this->command?->info('  Tema por defecto: '.DefaultThemeSeeder::DEFAULT_THEME_ID);
    }

    private function seedProcesses(): void
    {
        $data = require database_path('data/processes_mock.php');
        $now = Carbon::now();

        $rows = array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'alias' => $row['alias'],
            'description' => $row['description'] ?? null,
            'icon' => $row['icon'] ?? null,
            'color' => $row['color'] ?? null,
            'process_parent_id' => $row['process_parent_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $data['processes'] ?? []);

        // Primero los de primer nivel (la FK de los subprocesos apunta a ellos). Un conflicto
        // (mismo id o mismo código) deja la fila que ya había.
        $top = array_values(array_filter($rows, static fn (array $r): bool => $r['process_parent_id'] === null));
        $inserted = DB::table('processes')->insertOrIgnore($top);

        // Subprocesos solo si su padre existe (no se cuelgan de uno borrado ni de un id ajeno).
        $parentIds = DB::table('processes')->whereNull('deleted_at')->pluck('id')->all();
        $subs = array_values(array_filter(
            $rows,
            static fn (array $r): bool => $r['process_parent_id'] !== null && in_array($r['process_parent_id'], $parentIds, true),
        ));
        $inserted += DB::table('processes')->insertOrIgnore($subs);

        $this->command?->info('  Procesos: '.count($rows)." en el catálogo, {$inserted} nuevos");
    }
}
