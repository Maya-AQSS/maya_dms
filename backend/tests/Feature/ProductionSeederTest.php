<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Theme;
use Database\Seeders\DefaultThemeSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'processes' => DB::table('processes')->orderBy('code')
                ->get(['id', 'code', 'name', 'alias', 'process_parent_id', 'deleted_at'])
                ->map(static fn ($row) => (array) $row)
                ->all(),
            'themes' => Theme::query()->withTrashed()->orderBy('id')
                ->get(['id', 'name', 'created_by', 'is_system'])
                ->toArray(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        return (require database_path('data/processes_mock.php'))['processes'];
    }

    public function test_crea_el_mapa_de_procesos_y_el_tema_por_defecto(): void
    {
        // Las migraciones ya crean DEFAULT_PROCESS.
        $before = DB::table('processes')->count();

        $this->seed(ProductionSeeder::class);

        $this->assertSame($before + count($this->catalog()), DB::table('processes')->count());
        $this->assertSame(count($this->catalog()), DB::table('processes')
            ->whereIn('id', array_column($this->catalog(), 'id'))->count());
        $this->assertSame(1, Theme::query()->count());

        $theme = Theme::query()->findOrFail(DefaultThemeSeeder::DEFAULT_THEME_ID);
        $this->assertTrue($theme->is_system);
        $this->assertSame('published', $theme->status);
    }

    public function test_el_tema_no_depende_de_los_usuarios_de_desarrollo(): void
    {
        $this->seed(ProductionSeeder::class);

        $devUsers = require database_path('data/maya_dev_users.php');
        $theme = Theme::query()->findOrFail(DefaultThemeSeeder::DEFAULT_THEME_ID);

        $this->assertSame(DefaultThemeSeeder::SYSTEM_AUTHOR_ID, $theme->created_by);
        $this->assertNotContains($theme->created_by, array_values($devUsers));
    }

    public function test_es_idempotente(): void
    {
        $this->seed(ProductionSeeder::class);
        $first = $this->snapshot();

        $this->seed(ProductionSeeder::class);

        $this->assertSame($first, $this->snapshot());
    }

    public function test_no_pisa_procesos_editados_ni_resucita_los_borrados(): void
    {
        $before = DB::table('processes')->count();
        $this->seed(ProductionSeeder::class);
        [$edited, $deleted] = array_slice($this->catalog(), 0, 2);

        DB::table('processes')->where('id', $edited['id'])->update(['name' => 'Nombre del centro']);
        DB::table('processes')->where('id', $deleted['id'])->update(['deleted_at' => now()]);

        $this->seed(ProductionSeeder::class);

        $this->assertSame('Nombre del centro', DB::table('processes')->where('id', $edited['id'])->value('name'));
        $this->assertNotNull(DB::table('processes')->where('id', $deleted['id'])->value('deleted_at'));
        $this->assertSame($before + count($this->catalog()), DB::table('processes')->count());
    }

    public function test_no_cuelga_subprocesos_de_un_padre_borrado_a_mano(): void
    {
        $parent = collect($this->catalog())->first(static fn (array $p): bool => $p['process_parent_id'] === null);
        $children = collect($this->catalog())->where('process_parent_id', $parent['id']);
        $this->assertNotEmpty($children);

        // El centro ya tenía ese código con otro id: el del catálogo no entra, y sus hijos tampoco.
        DB::table('processes')->insert([
            'id' => '99999999-9999-4999-8999-999999999999',
            'code' => $parent['code'],
            'name' => 'Propio del centro',
            'alias' => 'Propio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(ProductionSeeder::class);

        $this->assertFalse(DB::table('processes')->where('id', $parent['id'])->exists());
        $this->assertSame(0, DB::table('processes')->whereIn('id', $children->pluck('id'))->count());
    }
}
