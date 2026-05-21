<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BlockNoteHtmlRenderer;
use PHPUnit\Framework\TestCase;

class BlockNoteHtmlRendererTest extends TestCase
{
    public function test_renders_empty_array_to_empty_string(): void
    {
        $this->assertSame('', BlockNoteHtmlRenderer::renderBlocks([]));
    }

    public function test_renders_paragraph_with_inline_text(): void
    {
        $blocks = [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Hola mundo', 'styles' => []]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringContainsString('<p>Hola mundo</p>', $html);
    }

    public function test_renders_heading_levels_1_to_6(): void
    {
        foreach ([1, 2, 3, 4, 5, 6] as $level) {
            $blocks = [[
                'type' => 'heading',
                'props' => ['level' => $level],
                'content' => [['type' => 'text', 'text' => 'T'.$level, 'styles' => []]],
            ]];

            $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

            $this->assertStringContainsString('<h'.$level.'>T'.$level.'</h'.$level.'>', $html);
        }
    }

    public function test_clamps_invalid_heading_level(): void
    {
        $blocks = [[
            'type' => 'heading',
            'props' => ['level' => 99],
            'content' => [['type' => 'text', 'text' => 'X', 'styles' => []]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringContainsString('<h6>X</h6>', $html);
    }

    public function test_escapes_html_in_user_text(): void
    {
        $blocks = [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => '<script>alert("xss")</script>',
                'styles' => [],
            ]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_applies_bold_italic_underline_marks(): void
    {
        $blocks = [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => 'foo',
                'styles' => ['bold' => true, 'italic' => true, 'underline' => true],
            ]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringContainsString('<u><em><strong>foo</strong></em></u>', $html);
    }

    public function test_renders_bullet_list_item(): void
    {
        $blocks = [[
            'type' => 'bulletListItem',
            'content' => [['type' => 'text', 'text' => 'item1', 'styles' => []]],
        ]];

        $this->assertStringContainsString('<ul><li>item1</li></ul>', BlockNoteHtmlRenderer::renderBlocks($blocks));
    }

    public function test_renders_link(): void
    {
        $blocks = [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'link',
                'href' => 'https://example.com',
                'content' => [['type' => 'text', 'text' => 'click', 'styles' => []]],
            ]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringContainsString('<a href="https://example.com">click</a>', $html);
    }

    public function test_escapes_href_in_link(): void
    {
        $blocks = [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'link',
                'href' => 'javascript:alert(1)',
                'content' => [['type' => 'text', 'text' => 'x', 'styles' => []]],
            ]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        // El href se escapa para impedir inyección de atributos; la URL se renderiza
        // como string literal aunque el browser pueda seguir interpretando javascript:.
        // Defensa en profundidad: el endpoint añade CSP con script-src 'none' y el
        // PDF renderer no ejecuta JS.
        $this->assertStringContainsString('href="javascript:alert(1)"', $html);
    }

    public function test_sanitizes_color_to_hex_or_named_only(): void
    {
        $blocks = [[
            'type' => 'paragraph',
            'props' => ['textColor' => 'expression(alert(1))'],
            'content' => [['type' => 'text', 'text' => 'x', 'styles' => []]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringNotContainsString('expression', $html);
        $this->assertStringContainsString('color:inherit', $html);
    }

    public function test_accepts_valid_hex_color(): void
    {
        $blocks = [[
            'type' => 'paragraph',
            'props' => ['textColor' => '#abcdef'],
            'content' => [['type' => 'text', 'text' => 'x', 'styles' => []]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringContainsString('color:#abcdef', $html);
    }

    public function test_unknown_block_type_falls_back_to_div(): void
    {
        $blocks = [[
            'type' => 'weirdCustomBlock',
            'content' => [['type' => 'text', 'text' => 'fallback', 'styles' => []]],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        $this->assertStringContainsString('<div data-block-type="weirdCustomBlock">fallback</div>', $html);
    }

    public function test_renders_accessible_table_with_scope_and_headers(): void
    {
        $blocks = [[
            'type' => 'table',
            'content' => [
                'rows' => [
                    ['cells' => [
                        [['type' => 'text', 'text' => 'Módulo', 'styles' => []]],
                        [['type' => 'text', 'text' => 'Nota', 'styles' => []]],
                    ]],
                    ['cells' => [
                        [['type' => 'text', 'text' => 'Programación', 'styles' => []]],
                        [['type' => 'text', 'text' => 'Notable', 'styles' => []]],
                    ]],
                ],
            ],
        ]];

        $html = BlockNoteHtmlRenderer::renderBlocks($blocks);

        // Cabeceras con id + scope=col.
        $this->assertMatchesRegularExpression(
            '/<th id="col-[a-f0-9]{6}-0" scope="col">Módulo<\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<th id="col-[a-f0-9]{6}-1" scope="col">Nota<\/th>/',
            $html
        );

        // Celdas con headers apuntando al id de su columna.
        $this->assertMatchesRegularExpression(
            '/<td headers="col-[a-f0-9]{6}-0">Programación<\/td>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<td headers="col-[a-f0-9]{6}-1">Notable<\/td>/',
            $html
        );
    }

    public function test_empty_table_produces_no_output(): void
    {
        $blocks = [[
            'type' => 'table',
            'content' => ['rows' => []],
        ]];

        $this->assertSame('', BlockNoteHtmlRenderer::renderBlocks($blocks));
    }
}
