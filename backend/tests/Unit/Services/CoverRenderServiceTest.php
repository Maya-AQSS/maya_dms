<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CoverRenderService;
use Tests\TestCase;

/**
 * Tests del CoverRenderService: render de bloque portada con elementos
 * posicionados de forma absoluta (mm→cm) y merge de valores de placeholders.
 */
class CoverRenderServiceTest extends TestCase
{
    private function svc(): CoverRenderService
    {
        return new CoverRenderService;
    }

    public function test_empty_geometry_returns_empty_string(): void
    {
        $this->assertSame('', $this->svc()->renderInner([], [], false));
        $this->assertSame('', $this->svc()->renderInner(['regions' => 'nope'], [], false));
    }

    public function test_renders_text_region_with_absolute_cm_position(): void
    {
        $cover = ['regions' => [
            ['id' => 't1', 'type' => 'text', 'box' => ['x' => 10, 'y' => 20, 'w' => 60, 'h' => 12, 'z' => 2],
                'props' => ['text' => 'Título', 'size' => 24, 'color' => '#0b5394', 'align' => 'center', 'weight' => 'bold']],
        ]];

        $html = $this->svc()->renderInner($cover, [], false);

        $this->assertStringContainsString('cover-el--text', $html);
        // mm/10 → cm
        $this->assertStringContainsString('left:1.0000cm;top:2.0000cm;width:6.0000cm;height:1.2000cm', $html);
        $this->assertStringContainsString('z-index:2', $html);
        $this->assertStringContainsString('font-size:24.00pt', $html);
        $this->assertStringContainsString('color:#0b5394', $html);
        $this->assertStringContainsString('text-align:center', $html);
        $this->assertStringContainsString('font-weight:700', $html);
        $this->assertStringContainsString('Título', $html);
    }

    public function test_placeholder_uses_document_value_when_present(): void
    {
        $cover = ['regions' => [
            ['id' => 'p1', 'type' => 'text_placeholder', 'box' => ['x' => 0, 'y' => 0, 'w' => 50, 'h' => 10],
                'props' => ['key' => 'autor', 'label' => 'Autor', 'defaultText' => 'Nombre del autor']],
        ]];

        $withValue = $this->svc()->renderInner($cover, ['autor' => 'Ada Lovelace'], false);
        $this->assertStringContainsString('Ada Lovelace', $withValue);
        $this->assertStringNotContainsString('Nombre del autor', $withValue);
    }

    public function test_placeholder_falls_back_to_default_text(): void
    {
        $cover = ['regions' => [
            ['id' => 'p1', 'type' => 'text_placeholder', 'box' => ['x' => 0, 'y' => 0, 'w' => 50, 'h' => 10],
                'props' => ['key' => 'autor', 'defaultText' => 'Nombre del autor']],
        ]];

        $html = $this->svc()->renderInner($cover, [], false);
        $this->assertStringContainsString('Nombre del autor', $html);
    }

    public function test_text_is_html_escaped(): void
    {
        $cover = ['regions' => [
            ['id' => 't1', 'type' => 'text', 'box' => ['x' => 0, 'y' => 0, 'w' => 50, 'h' => 10],
                'props' => ['text' => '<script>x</script>']],
        ]];

        $html = $this->svc()->renderInner($cover, [], false);
        $this->assertStringNotContainsString('<script>x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_invalid_color_and_align_fall_back_to_safe_defaults(): void
    {
        $cover = ['regions' => [
            ['id' => 't1', 'type' => 'text', 'box' => ['x' => 0, 'y' => 0, 'w' => 50, 'h' => 10],
                'props' => ['text' => 'X', 'color' => 'javascript:evil', 'align' => 'evil']],
        ]];

        $html = $this->svc()->renderInner($cover, [], false);
        $this->assertStringContainsString('color:#1a1a1a', $html);
        $this->assertStringContainsString('text-align:left', $html);
        $this->assertStringNotContainsString('javascript:evil', $html);
    }

    public function test_image_without_existing_asset_is_skipped(): void
    {
        $cover = ['regions' => [
            ['id' => 'i1', 'type' => 'image', 'box' => ['x' => 0, 'y' => 0, 'w' => 50, 'h' => 50],
                'props' => ['src' => 'does/not/exist.png']],
        ]];

        $html = $this->svc()->renderInner($cover, [], false);
        $this->assertStringNotContainsString('cover-el--image', $html);
    }

    public function test_page_number_emits_counter_spans(): void
    {
        $cover = ['regions' => [
            ['id' => 'pn', 'type' => 'page_number', 'box' => ['x' => 0, 'y' => 0, 'w' => 50, 'h' => 10],
                'props' => ['format' => 'page-of-pages']],
        ]];

        $html = $this->svc()->renderInner($cover, [], false);
        $this->assertStringContainsString('cover-pn', $html);
        $this->assertStringContainsString('cover-pt', $html);
    }

    public function test_region_without_box_is_ignored(): void
    {
        $cover = ['regions' => [
            ['id' => 'x', 'type' => 'text', 'props' => ['text' => 'sin caja']],
        ]];

        $this->assertSame('', $this->svc()->renderInner($cover, [], false));
    }
}
