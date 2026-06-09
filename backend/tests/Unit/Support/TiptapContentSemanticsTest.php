<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TiptapContentSemantics;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TiptapContentSemanticsTest extends TestCase
{
    #[Test]
    public function empty_paragraph_array_is_not_filled(): void
    {
        $content = [['type' => 'paragraph', 'content' => []]];

        $this->assertFalse(TiptapContentSemantics::isContentFilled($content));
    }

    #[Test]
    public function trailing_empty_paragraph_does_not_make_content_filled(): void
    {
        $content = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hola']]],
            ['type' => 'paragraph', 'content' => []],
        ];

        $this->assertTrue(TiptapContentSemantics::isContentFilled($content));
        $this->assertTrue(TiptapContentSemantics::contentEquals(
            $content,
            [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hola']]]],
        ));
    }

    #[Test]
    public function only_phantom_paragraphs_are_not_filled(): void
    {
        $this->assertFalse(TiptapContentSemantics::isContentFilled([
            ['type' => 'paragraph', 'content' => []],
        ]));
        $this->assertFalse(TiptapContentSemantics::isContentFilled('<p></p>'));
    }

    #[Test]
    public function table_colwidth_from_editor_does_not_change_content_equals(): void
    {
        $fromTemplate = [
            [
                'type' => 'table',
                'content' => [
                    [
                        'type' => 'tableRow',
                        'content' => [
                            [
                                'type' => 'tableCell',
                                'content' => [
                                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A']]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $afterEditor = [
            [
                'type' => 'table',
                'content' => [
                    [
                        'type' => 'tableRow',
                        'content' => [
                            [
                                'type' => 'tableCell',
                                'attrs' => ['colwidth' => [120]],
                                'content' => [
                                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A']]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertTrue(TiptapContentSemantics::contentEquals($fromTemplate, $afterEditor));
    }

    #[Test]
    public function image_width_height_from_editor_does_not_change_content_equals(): void
    {
        $fromTemplate = [
            ['type' => 'image', 'attrs' => ['src' => 'https://example.com/x.png', 'alt' => 'Logo']],
        ];
        $afterEditor = [
            ['type' => 'image', 'attrs' => ['src' => 'https://example.com/x.png', 'alt' => 'Logo', 'width' => 400]],
        ];

        $this->assertTrue(TiptapContentSemantics::contentEquals($fromTemplate, $afterEditor));
    }

    #[Test]
    public function paragraph_with_nested_image_is_filled(): void
    {
        $content = [
            ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['src' => 'https://x.test/a.png']]]],
        ];

        $this->assertTrue(TiptapContentSemantics::isContentFilled($content));
    }

    #[Test]
    public function phantom_empty_paragraph_inside_table_cell_does_not_change_content_equals(): void
    {
        $fromTemplate = [
            [
                'type' => 'table',
                'content' => [
                    [
                        'type' => 'tableRow',
                        'content' => [
                            [
                                'type' => 'tableCell',
                                'content' => [
                                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'c']]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $afterEditor = [
            [
                'type' => 'table',
                'content' => [
                    [
                        'type' => 'tableRow',
                        'content' => [
                            [
                                'type' => 'tableCell',
                                'attrs' => ['colwidth' => [100]],
                                'content' => [
                                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'c']]],
                                    ['type' => 'paragraph', 'content' => []],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertTrue(TiptapContentSemantics::contentEquals($fromTemplate, $afterEditor));
    }

    #[Test]
    public function empty_list_item_does_not_change_content_equals(): void
    {
        $fromTemplate = [
            [
                'type' => 'bulletList',
                'content' => [
                    [
                        'type' => 'listItem',
                        'content' => [
                            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Uno']]],
                        ],
                    ],
                ],
            ],
        ];
        $afterEditor = [
            [
                'type' => 'bulletList',
                'content' => [
                    [
                        'type' => 'listItem',
                        'content' => [
                            ['type' => 'paragraph', 'content' => []],
                        ],
                    ],
                    [
                        'type' => 'listItem',
                        'content' => [
                            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Uno']]],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertTrue(TiptapContentSemantics::contentEquals($fromTemplate, $afterEditor));
    }
}
