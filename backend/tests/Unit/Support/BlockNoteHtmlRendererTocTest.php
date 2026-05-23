<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BlockNoteHtmlRenderer;
use PHPUnit\Framework\TestCase;

class BlockNoteHtmlRendererTocTest extends TestCase
{
    public function test_toc_block_without_preceding_headings_renders_empty_list(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'toc',
                'content' => [],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        $this->assertStringContainsString('<section class="block-kind-toc">', $html);
        $this->assertStringContainsString('<ol class="toc"></ol>', $html);
    }

    public function test_content_block_with_headings_plus_toc_generates_toc_entries(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'content',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1],
                        'content' => [['type' => 'text', 'text' => 'Main Title']],
                    ],
                    [
                        'type' => 'paragraph',
                        'props' => [],
                        'content' => [['type' => 'text', 'text' => 'Some paragraph']],
                    ],
                    [
                        'type' => 'heading',
                        'props' => ['level' => 2],
                        'content' => [['type' => 'text', 'text' => 'Subtitle']],
                    ],
                ],
            ],
            [
                'kind' => 'toc',
                'content' => [],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // Verify TOC section exists
        $this->assertStringContainsString('<section class="block-kind-toc">', $html);
        $this->assertStringContainsString('<ol class="toc">', $html);

        // Verify TOC entries
        $this->assertStringContainsString('<li class="toc-h1">', $html);
        $this->assertStringContainsString('<li class="toc-h2">', $html);

        // Verify TOC links reference the heading IDs
        $this->assertStringContainsString('href="#block-0-h-0"', $html);
        $this->assertStringContainsString('href="#block-0-h-1"', $html);

        // Verify heading IDs are deterministic
        $this->assertStringContainsString('id="block-0-h-0"', $html);
        $this->assertStringContainsString('id="block-0-h-1"', $html);

        // Verify TOC text content
        $this->assertStringContainsString('Main Title', $html);
        $this->assertStringContainsString('Subtitle', $html);

        // Verify toc-page spans
        $this->assertStringContainsString('class="toc-page"', $html);
        $this->assertStringContainsString('data-href="#block-0-h-0"', $html);
        $this->assertStringContainsString('data-href="#block-0-h-1"', $html);
    }

    public function test_heading_ids_are_deterministic_across_renders(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'content',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1],
                        'content' => [['type' => 'text', 'text' => 'Title']],
                    ],
                ],
            ],
        ];

        // First render
        $html1 = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // Second render
        $html2 = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // Both outputs should be identical
        $this->assertEquals($html1, $html2);

        // Both should contain the same deterministic ID
        $this->assertStringContainsString('id="block-0-h-0"', $html1);
        $this->assertStringContainsString('id="block-0-h-0"', $html2);
    }

    public function test_multiple_content_blocks_each_have_their_own_heading_indices(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'content',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1],
                        'content' => [['type' => 'text', 'text' => 'First block heading']],
                    ],
                ],
            ],
            [
                'kind' => 'content',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1],
                        'content' => [['type' => 'text', 'text' => 'Second block heading']],
                    ],
                ],
            ],
            [
                'kind' => 'toc',
                'content' => [],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // First block heading: block-0-h-0
        $this->assertStringContainsString('id="block-0-h-0"', $html);
        $this->assertStringContainsString('First block heading', $html);

        // Second block heading: block-1-h-0
        $this->assertStringContainsString('id="block-1-h-0"', $html);
        $this->assertStringContainsString('Second block heading', $html);

        // TOC should include both
        $this->assertStringContainsString('href="#block-0-h-0"', $html);
        $this->assertStringContainsString('href="#block-1-h-0"', $html);
    }

    public function test_toc_only_collects_headings_from_content_blocks(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'cover',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1],
                        'content' => [['type' => 'text', 'text' => 'Cover Title']],
                    ],
                ],
            ],
            [
                'kind' => 'content',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1],
                        'content' => [['type' => 'text', 'text' => 'Content Title (included)']],
                    ],
                ],
            ],
            [
                'kind' => 'toc',
                'content' => [],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // TOC should only include the content block heading
        $this->assertStringContainsString('Content Title (included)', $html);

        // The TOC references only the content block heading (block-1-h-0), not the cover
        $this->assertStringContainsString('href="#block-1-h-0"', $html);
        // Cover heading should not have an ID assigned (it's not in a content block)
        $this->assertStringNotContainsString('block-0-h-', $html);
    }

    public function test_blank_block_receives_no_heading_ids(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'blank',
                'content' => [],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // Blank block should be an empty section with aria-hidden
        $this->assertStringContainsString('<section class="block-kind-blank" role="presentation" aria-hidden="true"></section>', $html);
    }

    public function test_heading_without_id_gets_deterministic_id_assigned(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'content',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1],
                        // No id in props
                        'content' => [['type' => 'text', 'text' => 'Title']],
                    ],
                ],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // Should get deterministic ID
        $this->assertStringContainsString('id="block-0-h-0"', $html);
    }

    public function test_heading_with_existing_id_preserves_it(): void
    {
        $blocksWithKind = [
            [
                'kind' => 'content',
                'content' => [
                    [
                        'type' => 'heading',
                        'props' => ['level' => 1, 'id' => 'my-custom-id'],
                        'content' => [['type' => 'text', 'text' => 'Title']],
                    ],
                ],
            ],
        ];

        $html = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // Should preserve the existing ID
        $this->assertStringContainsString('id="my-custom-id"', $html);
        // And should NOT generate a new one
        $this->assertStringNotContainsString('id="block-0-h-0"', $html);
    }
}
