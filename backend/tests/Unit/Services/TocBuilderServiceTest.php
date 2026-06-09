<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TocBuilderService;
use PHPUnit\Framework\TestCase;

/**
 * Tests del TocBuilderService (índice híbrido): entradas por bloque seleccionado
 * + subentradas por encabezado (opcional), inyectadas en el bloque `index`.
 */
class TocBuilderServiceTest extends TestCase
{
    private function svc(): TocBuilderService
    {
        return new TocBuilderService;
    }

    private function section(string $id, string $type, string $inner = ''): string
    {
        return '<section class="doc-block doc-block--'.$type.'" id="block-'.$id.'" data-block-type="'.$type.'">'.$inner.'</section>';
    }

    public function test_returns_html_unchanged_when_no_index_block(): void
    {
        $html = $this->section('a', 'content', '<h2>Intro</h2>');
        $blocks = [['id' => 'a', 'title' => 'Intro', 'block_type' => 'content', 'index_config' => null]];

        $this->assertSame($html, $this->svc()->build($html, $blocks));
    }

    public function test_injects_block_entries_for_selected_blocks(): void
    {
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h2>Capítulo 1</h2>').
            $this->section('b', 'content', '<h2>Capítulo 2</h2>');
        $blocks = [
            ['id' => 'idx', 'title' => 'Índice', 'block_type' => 'index', 'index_config' => ['kind' => 'index', 'blockIds' => ['a', 'b'], 'includeHeadings' => false]],
            ['id' => 'a', 'title' => 'Capítulo 1', 'block_type' => 'content', 'index_config' => null],
            ['id' => 'b', 'title' => 'Capítulo 2', 'block_type' => 'content', 'index_config' => null],
        ];

        $out = $this->svc()->build($html, $blocks);

        $this->assertStringContainsString('class="doc-toc"', $out);
        $this->assertStringContainsString('doc-toc__item--block', $out);
        $this->assertStringContainsString('href="#block-a"', $out);
        $this->assertStringContainsString('href="#block-b"', $out);
        $this->assertStringContainsString('Capítulo 1', $out);
        $this->assertStringContainsString('data-target="#block-a"', $out);
    }

    public function test_defaults_to_all_content_blocks_when_no_block_ids(): void
    {
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h2>Uno</h2>').
            $this->section('cv', 'cover', '');
        $blocks = [
            ['id' => 'idx', 'title' => 'Índice', 'block_type' => 'index', 'index_config' => ['kind' => 'index', 'blockIds' => [], 'includeHeadings' => false]],
            ['id' => 'a', 'title' => 'Uno', 'block_type' => 'content', 'index_config' => null],
            ['id' => 'cv', 'title' => 'Portada', 'block_type' => 'cover', 'index_config' => null],
        ];

        $out = $this->svc()->build($html, $blocks);

        // Por defecto entran los bloques de contenido, no la portada.
        $this->assertStringContainsString('href="#block-a"', $out);
        $this->assertStringNotContainsString('href="#block-cv"', $out);
    }

    public function test_include_headings_adds_subentries(): void
    {
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h1>Sección</h1><h2>Subsección</h2>');
        $blocks = [
            ['id' => 'idx', 'title' => 'Índice', 'block_type' => 'index', 'index_config' => ['kind' => 'index', 'blockIds' => ['a'], 'includeHeadings' => true]],
            ['id' => 'a', 'title' => 'Bloque A', 'block_type' => 'content', 'index_config' => null],
        ];

        $out = $this->svc()->build($html, $blocks);

        // Entrada de bloque + subentradas de encabezado.
        $this->assertStringContainsString('href="#block-a"', $out);
        $this->assertStringContainsString('doc-toc__item--h1', $out);
        $this->assertStringContainsString('Sección', $out);
        $this->assertStringContainsString('Subsección', $out);
        // Los encabezados reciben id para anclarse.
        $this->assertMatchesRegularExpression('/<h1 id="doc-toc-\d+">Sección<\/h1>/', $out);
    }

    public function test_headings_not_added_when_include_headings_false(): void
    {
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h1>Sección</h1>');
        $blocks = [
            ['id' => 'idx', 'title' => 'Índice', 'block_type' => 'index', 'index_config' => ['kind' => 'index', 'blockIds' => ['a'], 'includeHeadings' => false]],
            ['id' => 'a', 'title' => 'Bloque A', 'block_type' => 'content', 'index_config' => null],
        ];

        $out = $this->svc()->build($html, $blocks);

        $this->assertStringNotContainsString('doc-toc__item--h1', $out);
    }

    public function test_index_does_not_reference_itself_and_respects_order(): void
    {
        $html =
            $this->section('b', 'content', '<h2>B</h2>').
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h2>A</h2>');
        $blocks = [
            ['id' => 'b', 'title' => 'B', 'block_type' => 'content', 'index_config' => null],
            ['id' => 'idx', 'title' => 'Índice', 'block_type' => 'index', 'index_config' => ['kind' => 'index', 'blockIds' => ['a', 'idx', 'b'], 'includeHeadings' => false]],
            ['id' => 'a', 'title' => 'A', 'block_type' => 'content', 'index_config' => null],
        ];

        $out = $this->svc()->build($html, $blocks);

        // No se referencia a sí mismo.
        $this->assertStringNotContainsString('href="#block-idx"', $out);
        // Orden del documento: B (pos 0) antes que A (pos 2).
        $this->assertLessThan(strpos($out, 'href="#block-a"'), strpos($out, 'href="#block-b"'));
    }
}
