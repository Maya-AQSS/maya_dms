<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\BlockKind;
use App\Support\BlockNoteHtmlRenderer;
use PHPUnit\Framework\TestCase;

class RenderHtmlSnapshotTest extends TestCase
{
    public function test_single_content_block_renders_with_block_kind_section(): void
    {
        $blocksWithKind = [
            [
                'kind' => BlockKind::Content->value,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido de prueba']]],
                ],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        $this->assertStringContainsString('<section class="block-kind-content">', $html);
        $this->assertStringContainsString('Contenido de prueba', $html);
        $this->assertStringNotContainsString('block-kind-cover', $html);
        $this->assertStringNotContainsString('block-kind-blank', $html);
        $this->assertStringNotContainsString('block-kind-toc', $html);
    }

    public function test_cover_plus_content_blocks_render_in_order(): void
    {
        $blocksWithKind = [
            [
                'kind' => BlockKind::Cover->value,
                'content' => [
                    ['type' => 'heading', 'props' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Portada del documento']]],
                ],
            ],
            [
                'kind' => BlockKind::Content->value,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido después de portada']]],
                ],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        $coverPos = strpos($html, 'block-kind-cover');
        $contentPos = strpos($html, 'block-kind-content');
        $this->assertNotFalse($coverPos);
        $this->assertNotFalse($contentPos);
        $this->assertLessThan($contentPos, $coverPos);

        $this->assertStringContainsString('Portada del documento', $html);
        $this->assertStringContainsString('Contenido después de portada', $html);
    }

    public function test_content_plus_toc_plus_content_generates_toc_with_headings(): void
    {
        $blocksWithKind = [
            [
                'kind' => BlockKind::Content->value,
                'content' => [
                    ['type' => 'heading', 'props' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Primer título']]],
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Párrafo intermedio']]],
                    ['type' => 'heading', 'props' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Subtítulo']]],
                ],
            ],
            [
                'kind' => BlockKind::Toc->value,
                'content' => [],
            ],
            [
                'kind' => BlockKind::Content->value,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Más contenido']]],
                ],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        $this->assertStringContainsString('<section class="block-kind-toc">', $html);
        $this->assertStringContainsString('<ol class="toc">', $html);

        $this->assertStringContainsString('id="block-0-h-0"', $html);
        $this->assertStringContainsString('id="block-0-h-1"', $html);

        $this->assertStringContainsString('href="#block-0-h-0"', $html);
        $this->assertStringContainsString('href="#block-0-h-1"', $html);

        $this->assertStringContainsString('class="toc-h1"', $html);
        $this->assertStringContainsString('class="toc-h2"', $html);
    }

    public function test_blank_block_renders_with_aria_hidden(): void
    {
        $blocksWithKind = [
            [
                'kind' => BlockKind::Content->value,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido antes']]],
                ],
            ],
            [
                'kind' => BlockKind::Blank->value,
                'content' => [],
            ],
            [
                'kind' => BlockKind::Content->value,
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido después']]],
                ],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        $this->assertStringContainsString('<section class="block-kind-blank" role="presentation" aria-hidden="true"></section>', $html);

        $this->assertStringContainsString('Contenido antes', $html);
        $this->assertStringContainsString('Contenido después', $html);
    }
}
