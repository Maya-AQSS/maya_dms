<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TemplateBlockDescriptionNormalizer;
use PHPUnit\Framework\TestCase;

final class TemplateBlockDescriptionNormalizerTest extends TestCase
{
    public function test_null_and_empty_string_return_null(): void
    {
        $this->assertNull(TemplateBlockDescriptionNormalizer::toPlainString(null));
        $this->assertNull(TemplateBlockDescriptionNormalizer::toPlainString(''));
        $this->assertNull(TemplateBlockDescriptionNormalizer::toPlainString('   '));
    }

    public function test_plain_string_passthrough(): void
    {
        $this->assertSame('Hola revisor', TemplateBlockDescriptionNormalizer::toPlainString('  Hola revisor  '));
    }

    public function test_doc_root_blocknote_to_plain_text(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Uno', 'styles' => []]],
                'children' => [],
            ], [
                'type' => 'paragraph',
                'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Dos', 'styles' => []]],
                'children' => [],
            ]],
        ];

        $this->assertSame("Uno\n\nDos", TemplateBlockDescriptionNormalizer::toPlainString($doc));
    }

    public function test_json_string_doc_legacy(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => [],
                'content' => [['type' => 'text', 'text' => 'Solo párrafo', 'styles' => []]],
                'children' => [],
            ]],
        ];
        $json = json_encode($doc, JSON_UNESCAPED_UNICODE);
        $this->assertSame('Solo párrafo', TemplateBlockDescriptionNormalizer::toPlainString($json));
    }
}
