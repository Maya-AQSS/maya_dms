<?php

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
}
