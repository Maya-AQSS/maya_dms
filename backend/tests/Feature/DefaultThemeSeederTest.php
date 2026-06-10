<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\DocumentConstants;
use App\Models\Theme;
use Database\Seeders\DefaultThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultThemeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_a_published_protected_default_theme_from_constant(): void
    {
        $this->seed(DefaultThemeSeeder::class);

        $theme = Theme::query()->find(DefaultThemeSeeder::DEFAULT_THEME_ID);

        $this->assertNotNull($theme);
        $this->assertSame('published', $theme->status);
        $this->assertTrue($theme->is_system);
        $this->assertSame(DocumentConstants::DEFAULT_THEME['palette'], $theme->palette);
        $this->assertSame(DocumentConstants::DEFAULT_THEME['typography'], $theme->typography);
    }

    public function test_seeds_visible_layout_regions(): void
    {
        $this->seed(DefaultThemeSeeder::class);

        $theme = Theme::query()->find(DefaultThemeSeeder::DEFAULT_THEME_ID);
        $regions = $theme->layout['regions'] ?? [];

        $this->assertNotEmpty($regions, 'El tema por defecto debe traer regiones visibles.');

        $types = array_map(static fn (array $r): string => $r['type'] ?? '', $regions);
        $this->assertContains('content_slot', $types);
        $this->assertContains('text', $types);
        $this->assertContains('page_number', $types);

        // Cada región debe llevar caja en mm para que el editor/preview la posicione.
        foreach ($regions as $r) {
            $this->assertArrayHasKey('box', $r);
            $this->assertArrayHasKey('x', $r['box']);
            $this->assertArrayHasKey('y', $r['box']);
            $this->assertArrayHasKey('w', $r['box']);
            $this->assertArrayHasKey('h', $r['box']);
        }
    }

    public function test_is_idempotent(): void
    {
        $this->seed(DefaultThemeSeeder::class);
        $this->seed(DefaultThemeSeeder::class);

        $this->assertSame(1, Theme::query()->where('id', DefaultThemeSeeder::DEFAULT_THEME_ID)->count());
    }

    public function test_preserves_admin_edits_on_reseed(): void
    {
        $this->seed(DefaultThemeSeeder::class);

        Theme::query()->where('id', DefaultThemeSeeder::DEFAULT_THEME_ID)
            ->update(['name' => 'Editado por admin']);

        $this->seed(DefaultThemeSeeder::class);

        $theme = Theme::query()->find(DefaultThemeSeeder::DEFAULT_THEME_ID);
        $this->assertSame('Editado por admin', $theme->name);
    }
}
