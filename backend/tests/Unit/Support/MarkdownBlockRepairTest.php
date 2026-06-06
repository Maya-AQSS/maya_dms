<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MarkdownBlockRepair;
use PHPUnit\Framework\TestCase;

class MarkdownBlockRepairTest extends TestCase
{
    /** @param array<int,array<string,mixed>> $content */
    private function json(array $content): string
    {
        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function test_fixes_inline_bold_in_heading_keeping_wrapper(): void
    {
        $content = [
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [
                ['type' => 'text', 'text' => 'CICLO DE **NOMBRE_DEL_CICLO**'],
            ]],
        ];
        $out = MarkdownBlockRepair::repair($content);
        $this->assertTrue($out['changed']);
        $this->assertSame('heading', $out['content'][0]['type']);
        $this->assertSame(1, $out['content'][0]['attrs']['level']);
        $json = $this->json($out['content']);
        $this->assertStringContainsString('"bold"', $json);
        $this->assertStringContainsString('NOMBRE_DEL_CICLO', $json);
        $this->assertStringNotContainsString('**', $json);
    }

    public function test_merges_existing_marks_when_node_already_bold(): void
    {
        $content = [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Departamento: **NOMBRE**', 'marks' => [['type' => 'bold']]],
            ]],
        ];
        $out = MarkdownBlockRepair::repair($content);
        $this->assertTrue($out['changed']);
        $json = $this->json($out['content']);
        $this->assertStringNotContainsString('**', $json);
        // "Departamento: " keeps the original bold; "NOMBRE" stays bold (deduped).
        $this->assertStringContainsString('Departamento: ', $json);
        $this->assertStringContainsString('NOMBRE', $json);
    }

    public function test_splices_block_markdown_paragraph_into_heading_and_list(): void
    {
        $content = [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => "## Programa\n\n1. uno\n2. dos"],
            ]],
        ];
        $out = MarkdownBlockRepair::repair($content);
        $this->assertTrue($out['changed']);
        $types = array_column($out['content'], 'type');
        $this->assertContains('heading', $types);
        $this->assertContains('orderedList', $types);
    }

    public function test_leaves_codeblock_alone_by_default(): void
    {
        $content = [
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => '## not a heading **x**']]],
        ];
        $out = MarkdownBlockRepair::repair($content);
        $this->assertFalse($out['changed']);
        $this->assertSame('codeBlock', $out['content'][0]['type']);
    }

    public function test_converts_codeblock_when_opted_in(): void
    {
        $content = [
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => "## Programa\n\nA **B**"]]],
        ];
        $out = MarkdownBlockRepair::repair($content, includeCodeBlocks: true);
        $this->assertTrue($out['changed']);
        $types = array_column($out['content'], 'type');
        $this->assertContains('heading', $types);
        $this->assertStringNotContainsString('<pre', $this->json($out['content']));
    }

    public function test_recurses_into_list_items(): void
    {
        $content = [
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'item con **negrita**']]],
                ]],
            ]],
        ];
        $out = MarkdownBlockRepair::repair($content);
        $this->assertTrue($out['changed']);
        $this->assertStringContainsString('"bold"', $this->json($out['content']));
        $this->assertSame('bulletList', $out['content'][0]['type']);
    }

    public function test_is_noop_on_clean_content(): void
    {
        $content = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'texto normal sin formato']]],
        ];
        $out = MarkdownBlockRepair::repair($content);
        $this->assertFalse($out['changed']);
        $this->assertSame($content, $out['content']);
    }

    public function test_is_idempotent(): void
    {
        $content = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'X **Y**']]],
        ];
        $first = MarkdownBlockRepair::repair($content);
        $second = MarkdownBlockRepair::repair($first['content']);
        $this->assertTrue($first['changed']);
        $this->assertFalse($second['changed']);
    }
}
