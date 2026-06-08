<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\Templates\TemplateDto;
use App\Models\Template;
use App\Models\Theme;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tests del payload de Theme dentro de `TemplateDto`. No tocan BD: usamos
 * modelos Eloquent in-memory con `setRelation()` para simular eager-load.
 */
class TemplateDtoThemeTest extends TestCase
{
    private function baseTemplate(string $id = '00000000-0000-0000-0000-000000000001'): Template
    {
        $t = new Template;
        $t->id = $id;
        $t->name = 'Plantilla X';
        $t->description = null;
        $t->status = 'draft';
        $t->version = 1;
        $t->review_stages = 0;
        $t->review_mode = 'parallel';
        $t->process_id = '00000000-0000-0000-0000-000000000010';
        $t->created_by = '00000000-0000-0000-0000-000000000020';
        $t->created_at = Carbon::parse('2026-01-01T12:00:00Z');
        $t->updated_at = Carbon::parse('2026-01-02T13:00:00Z');

        return $t;
    }

    public function test_dto_carries_theme_id_when_set(): void
    {
        $t = $this->baseTemplate();
        $t->theme_id = '00000000-0000-0000-0000-000000000099';

        $dto = TemplateDto::fromModel($t);

        $this->assertSame('00000000-0000-0000-0000-000000000099', $dto->themeId);
        $this->assertNull($dto->themeMini, 'Sin relación cargada → mini debe ser null');
    }

    public function test_dto_emits_theme_mini_when_relation_loaded(): void
    {
        $t = $this->baseTemplate();
        $t->theme_id = '00000000-0000-0000-0000-000000000099';

        $theme = new Theme;
        $theme->id = '00000000-0000-0000-0000-000000000099';
        $theme->name = 'Tema corporativo';
        $theme->palette = [
            'primary' => '#0b5394', 'secondary' => '#666', 'accent' => '#f59e0b',
            'background' => '#fff', 'text' => '#1a1a1a',
        ];
        $theme->typography = ['heading_font' => 'Inter, sans-serif', 'body_font' => 'Inter, sans-serif', 'base_size_pt' => 11, 'line_height' => 1.5];

        $t->setRelation('theme', $theme);

        $dto = TemplateDto::fromModel($t);

        $this->assertNotNull($dto->themeMini);
        $this->assertSame('Tema corporativo', $dto->themeMini['name']);
        $this->assertSame('#0b5394', $dto->themeMini['palette']['primary']);
        $this->assertSame('#f59e0b', $dto->themeMini['palette']['accent']);
        $this->assertSame('Inter, sans-serif', $dto->themeMini['typography']['heading_font']);
    }

    public function test_dto_emits_null_theme_when_no_relation(): void
    {
        $t = $this->baseTemplate();
        // sin theme_id, sin relación
        $dto = TemplateDto::fromModel($t);

        $this->assertNull($dto->themeId);
        $this->assertNull($dto->themeMini);
    }

    public function test_dto_handles_partial_palette_gracefully(): void
    {
        $t = $this->baseTemplate();
        $theme = new Theme;
        $theme->id = '00000000-0000-0000-0000-000000000099';
        $theme->name = 'Tema parcial';
        $theme->palette = ['primary' => '#000']; // sólo primary
        $theme->typography = null;
        $t->setRelation('theme', $theme);

        $dto = TemplateDto::fromModel($t);

        $this->assertNotNull($dto->themeMini);
        $this->assertSame('#000', $dto->themeMini['palette']['primary']);
        $this->assertNull($dto->themeMini['palette']['secondary']);
        $this->assertNull($dto->themeMini['typography']['heading_font']);
    }
}
