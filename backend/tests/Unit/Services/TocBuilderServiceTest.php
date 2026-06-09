<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TocBuilderService;
use PHPUnit\Framework\TestCase;

/**
 * Tests del TocBuilderService. Modelo: las entradas del índice son los TÍTULOS
 * INTERNOS (encabezados H1–H3) del contenido de TODOS los bloques (en orden del
 * documento), NO el nombre del bloque. La config es una deny-list
 * `{ kind:'index', excludedHeadings:[ "{blockId}#{idx}" ] }`; por defecto entran
 * todos. La indentación se normaliza (el nivel más alto presente pasa a h1).
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

    /** @param array<string,mixed> $cfg */
    private function indexBlock(string $id, array $cfg = []): array
    {
        return ['id' => $id, 'title' => 'Índice', 'block_type' => 'index', 'index_config' => ['kind' => 'index'] + $cfg];
    }

    private function contentBlock(string $id, string $title = 'Bloque'): array
    {
        return ['id' => $id, 'title' => $title, 'block_type' => 'content', 'index_config' => null];
    }

    public function test_returns_html_unchanged_when_no_index_block(): void
    {
        $html = $this->section('a', 'content', '<h2>Intro</h2>');
        $blocks = [$this->contentBlock('a')];

        $this->assertSame($html, $this->svc()->build($html, $blocks));
    }

    public function test_uses_internal_headings_of_all_blocks_not_names(): void
    {
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h2>Capítulo Uno</h2>').
            $this->section('b', 'content', '<h2>Capítulo Dos</h2>');
        $blocks = [
            $this->indexBlock('idx'),
            ['id' => 'a', 'title' => 'NombreBloqueA', 'block_type' => 'content', 'index_config' => null],
            ['id' => 'b', 'title' => 'NombreBloqueB', 'block_type' => 'content', 'index_config' => null],
        ];

        $out = $this->svc()->build($html, $blocks);

        $this->assertStringContainsString('class="doc-toc"', $out);
        $this->assertStringContainsString('Capítulo Uno', $out);
        $this->assertStringContainsString('Capítulo Dos', $out);
        $this->assertStringNotContainsString('NombreBloqueA', $out);
        $this->assertStringNotContainsString('href="#block-a"', $out);
        $this->assertMatchesRegularExpression('/<h2 id="doc-toc-\d+">Capítulo Uno<\/h2>/', $out);
    }

    public function test_excludes_block_title_heading_from_index(): void
    {
        // El render emite el NOMBRE del bloque como <h2 class="doc-block-title">
        // seguido del contenido. El índice debe listar solo el encabezado interno
        // del contenido, no el título del bloque (espeja el preview de edición).
        $html =
            $this->section('idx', 'index', '<h2 class="doc-block-title">Índice</h2>').
            $this->section('a', 'content', '<h2 class="doc-block-title">Modalidad</h2><h1>Curso semipresencial</h1>');
        $blocks = [$this->indexBlock('idx'), $this->contentBlock('a', 'Modalidad')];

        $out = $this->svc()->build($html, $blocks);

        $this->assertStringContainsString('doc-toc__text">Curso semipresencial', $out);
        $this->assertStringNotContainsString('doc-toc__text">Modalidad', $out);
        // Una sola entrada: el título de bloque no cuenta.
        $this->assertSame(1, substr_count($out, 'doc-toc__link'));
    }

    public function test_excludes_headings_in_deny_list(): void
    {
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h1>Principal</h1><h2>Oculto</h2>');
        $blocks = [
            // Excluye el 2º encabezado (idx 1) del bloque a.
            $this->indexBlock('idx', ['excludedHeadings' => ['a#1']]),
            $this->contentBlock('a'),
        ];

        $out = $this->svc()->build($html, $blocks);

        $this->assertStringContainsString('doc-toc__text">Principal', $out);
        $this->assertStringNotContainsString('doc-toc__text">Oculto', $out);
    }

    public function test_block_without_internal_headings_contributes_nothing(): void
    {
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<p>Solo párrafos.</p>');
        $blocks = [$this->indexBlock('idx'), $this->contentBlock('a')];

        $out = $this->svc()->build($html, $blocks);

        // Sin encabezados internos no se construye nav y el HTML vuelve intacto.
        $this->assertStringNotContainsString('class="doc-toc"', $out);
    }

    public function test_respects_document_order_and_excludes_self(): void
    {
        $html =
            $this->section('b', 'content', '<h2>Capítulo B</h2>').
            $this->section('idx', 'index', '<h2>Índice propio</h2>').
            $this->section('a', 'content', '<h2>Capítulo A</h2>');
        $blocks = [
            $this->contentBlock('b'),
            $this->indexBlock('idx'),
            $this->contentBlock('a'),
        ];

        $out = $this->svc()->build($html, $blocks);

        // Orden del documento: B (pos 0) antes que A (pos 2).
        $this->assertLessThan(
            strpos($out, 'doc-toc__text">Capítulo A'),
            strpos($out, 'doc-toc__text">Capítulo B'),
        );
        // El índice no se incluye a sí mismo: solo 2 entradas.
        $this->assertSame(2, substr_count($out, 'doc-toc__link'));
    }

    public function test_normalizes_indentation_to_h1(): void
    {
        // Todos los encabezados son H2 → tras normalizar el más alto pasa a h1.
        $html =
            $this->section('idx', 'index', '<h2>Índice</h2>').
            $this->section('a', 'content', '<h2>Solo H2</h2>');
        $blocks = [$this->indexBlock('idx'), $this->contentBlock('a')];

        $out = $this->svc()->build($html, $blocks);

        $this->assertStringContainsString('doc-toc__item--h1', $out);
        $this->assertStringNotContainsString('doc-toc__item--h2', $out);
    }
}
