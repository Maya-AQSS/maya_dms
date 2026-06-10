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

    // ─── JSON string starting with '[' ───────────────────────────────────────

    public function test_json_string_starting_with_bracket_decoded_as_list(): void
    {
        // A JSON string encoding a list array — hits the '[' branch in toPlainString
        $list = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item uno']], 'children' => []],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item dos']], 'children' => []],
        ];
        $json = json_encode($list, JSON_UNESCAPED_UNICODE);
        $result = TemplateBlockDescriptionNormalizer::toPlainString($json);
        $this->assertSame("Item uno\n\nItem dos", $result);
    }

    public function test_json_string_non_json_returns_as_is(): void
    {
        // A non-empty string that doesn't start with '{' or '[' is returned trimmed
        $result = TemplateBlockDescriptionNormalizer::toPlainString('  texto plano  ');
        $this->assertSame('texto plano', $result);
    }

    // ─── Non-string, non-array input ─────────────────────────────────────────

    public function test_non_string_non_array_returns_null(): void
    {
        // Integers, booleans, floats are not arrays or strings → null (line 44)
        $this->assertNull(TemplateBlockDescriptionNormalizer::toPlainString(42));
        $this->assertNull(TemplateBlockDescriptionNormalizer::toPlainString(3.14));
        $this->assertNull(TemplateBlockDescriptionNormalizer::toPlainString(true));
    }

    // ─── bulletListItem and numberedListItem ──────────────────────────────────

    public function test_bullet_list_item_with_content_returns_text(): void
    {
        $node = [
            'type' => 'bulletListItem',
            'content' => [['type' => 'text', 'text' => 'Viñeta uno']],
            'children' => [],
        ];
        $result = TemplateBlockDescriptionNormalizer::toPlainString($node);
        $this->assertSame('Viñeta uno', $result);
    }

    public function test_numbered_list_item_with_children_appends_child_text(): void
    {
        // children is a list of child blocks — hits the children branch (line 65)
        $node = [
            'type' => 'numberedListItem',
            'content' => [['type' => 'text', 'text' => 'Elemento principal']],
            'children' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Sub-elemento']], 'children' => []],
            ],
        ];
        $result = TemplateBlockDescriptionNormalizer::toPlainString($node);
        $this->assertStringContainsString('Elemento principal', $result);
        $this->assertStringContainsString('Sub-elemento', $result);
    }

    // ─── array_is_list path ──────────────────────────────────────────────────

    public function test_list_array_without_type_key_collapses_blocks(): void
    {
        // An array that is_list and has no 'type' key → array_is_list branch (line 75)
        $list = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Primero']], 'children' => []],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Segundo']], 'children' => []],
        ];
        $result = TemplateBlockDescriptionNormalizer::toPlainString($list);
        $this->assertSame("Primero\n\nSegundo", $result);
    }

    // ─── Associative array with content/children but unknown type ────────────

    public function test_associative_array_with_content_key_extracts_text(): void
    {
        // Associative array, not doc/bullet/paragraph, not a list → falls through to
        // the 'content'/'children' fallback (lines 79-87)
        $node = [
            'customType' => 'something',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Contenido']], 'children' => []],
            ],
        ];
        $result = TemplateBlockDescriptionNormalizer::toPlainString($node);
        $this->assertSame('Contenido', $result);
    }

    public function test_associative_array_with_children_key_extracts_text(): void
    {
        // Associative array with 'children' key but no 'content' key
        $node = [
            'id' => 'block-xyz',
            'children' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hijo']], 'children' => []],
            ],
        ];
        $result = TemplateBlockDescriptionNormalizer::toPlainString($node);
        $this->assertSame('Hijo', $result);
    }

    // ─── extractInlineText edge cases ────────────────────────────────────────

    public function test_inline_content_with_non_text_type_falls_back_to_extract(): void
    {
        // An inline item that is not 'text' type → hits the else branch (line 122-123)
        $node = [
            'type' => 'paragraph',
            'content' => [
                // non-text inline type → extractFromLegacyStructure called recursively
                ['type' => 'hardBreak'],
                ['type' => 'text', 'text' => 'Después de salto'],
            ],
            'children' => [],
        ];
        $result = TemplateBlockDescriptionNormalizer::toPlainString($node);
        // hardBreak returns '' from extractFromLegacyStructure; paragraph text is returned
        $this->assertSame('Después de salto', $result);
    }

    public function test_inline_content_with_non_array_item_is_skipped(): void
    {
        // extractInlineText: non-array items are skipped (line 117-118)
        $node = [
            'type' => 'paragraph',
            'content' => [
                'not-an-array',  // scalar — skipped
                ['type' => 'text', 'text' => 'Solo esto'],
            ],
            'children' => [],
        ];
        $result = TemplateBlockDescriptionNormalizer::toPlainString($node);
        $this->assertSame('Solo esto', $result);
    }

    public function test_empty_array_returns_null(): void
    {
        // An empty array → extractFromLegacyStructure returns '' → null
        $result = TemplateBlockDescriptionNormalizer::toPlainString([]);
        $this->assertNull($result);
    }

    // ─── toTiptapDoc: forma rich-text (array) esperada por el cast/frontend ───

    public function test_to_tiptap_doc_null_and_empty_return_null(): void
    {
        $this->assertNull(TemplateBlockDescriptionNormalizer::toTiptapDoc(null));
        $this->assertNull(TemplateBlockDescriptionNormalizer::toTiptapDoc(''));
        $this->assertNull(TemplateBlockDescriptionNormalizer::toTiptapDoc('   '));
        $this->assertNull(TemplateBlockDescriptionNormalizer::toTiptapDoc([]));
    }

    public function test_to_tiptap_doc_wraps_plain_prose_in_paragraph(): void
    {
        $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc('  Guía para el revisor  ');

        $this->assertSame([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Guía para el revisor']],
            ]],
        ], $doc);
    }

    public function test_to_tiptap_doc_splits_blank_lines_into_paragraphs(): void
    {
        $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc("Uno\n\nDos");

        $this->assertCount(2, $doc['content']);
        $this->assertSame('Uno', $doc['content'][0]['content'][0]['text']);
        $this->assertSame('Dos', $doc['content'][1]['content'][0]['text']);
    }

    public function test_to_tiptap_doc_passthrough_clean_tiptap_doc(): void
    {
        $tiptap = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Negrita', 'marks' => [['type' => 'bold']]]],
            ]],
        ];

        // Se preserva tal cual (no se aplana el rich text creado en la UI).
        $this->assertSame($tiptap, TemplateBlockDescriptionNormalizer::toTiptapDoc($tiptap));
    }

    public function test_to_tiptap_doc_flattens_legacy_blocknote_doc(): void
    {
        // Doc BlockNote (usa props/styles/children) → se aplana a párrafos Tiptap limpios.
        $blocknote = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'props' => ['textAlignment' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Texto legado', 'styles' => []]],
                'children' => [],
            ]],
        ];

        $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc($blocknote);

        $this->assertSame('Texto legado', $doc['content'][0]['content'][0]['text']);
        $this->assertArrayNotHasKey('props', $doc['content'][0]);
    }

    public function test_to_tiptap_doc_decodes_json_string(): void
    {
        $json = json_encode([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Desde JSON']],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc($json);

        $this->assertSame('Desde JSON', $doc['content'][0]['content'][0]['text']);
    }

    public function test_to_tiptap_doc_preserves_peeled_tiptap_node_list(): void
    {
        // Forma EXACTA que emite MayaEditorPanel por el cable: el array `content`
        // pelado (sin envoltorio `{type:doc}`). Antes caía en la rama legacy y se
        // aplanaba a texto plano, perdiendo marcas, imágenes, tablas e iframes.
        $peeled = [
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Negrita', 'marks' => [['type' => 'bold']]]],
            ],
            ['type' => 'image', 'attrs' => ['src' => 'https://x/y.png', 'alt' => 'img']],
            ['type' => 'iframe', 'attrs' => ['src' => 'https://youtube.com/embed/abc', 'width' => '100%', 'height' => '400']],
        ];

        $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc($peeled);

        $this->assertSame('doc', $doc['type']);
        $this->assertSame(['paragraph', 'image', 'iframe'], array_column($doc['content'], 'type'));
        // La marca bold y los nodos no textuales sobreviven (no se aplanan).
        $this->assertSame('bold', $doc['content'][0]['content'][0]['marks'][0]['type']);
        $this->assertSame('https://youtube.com/embed/abc', $doc['content'][2]['attrs']['src']);
    }

    public function test_to_tiptap_doc_flattens_peeled_blocknote_node_list(): void
    {
        // Una lista pelada BlockNote (props/styles/children) SIGUE aplanándose.
        $blocknoteList = [
            [
                'type' => 'paragraph',
                'props' => ['textAlignment' => 'left'],
                'content' => [['type' => 'text', 'text' => 'Legado', 'styles' => []]],
                'children' => [],
            ],
        ];

        $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc($blocknoteList);

        $this->assertSame('Legado', $doc['content'][0]['content'][0]['text']);
        $this->assertArrayNotHasKey('props', $doc['content'][0]);
        $this->assertArrayNotHasKey('children', $doc['content'][0]);
    }
}
