<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Tests de smoke contra el Blade `documents.render`. Verifica los dos modos
 * de render (legacy sin grid / con grid del editor de rejilla) sin depender
 * de la base de datos — usamos el view facade directamente con arrays
 * sintéticos, igual que en producción.
 */
class DocumentsRenderViewTest extends TestCase
{
    private function baseTheme(array $overrides = []): array
    {
        return array_replace_recursive([
            'palette' => [
                'primary' => '#0b5394', 'secondary' => '#666', 'text' => '#1a1a1a',
                'background' => '#fff', 'accent' => '#f59e0b',
            ],
            'typography' => [
                'heading_font' => 'sans-serif', 'body_font' => 'sans-serif',
                'base_size_pt' => 11, 'line_height' => 1.5,
            ],
            'layout' => [
                'regions' => [],
                'page' => ['size' => 'A4', 'margin_cm' => ['top' => 2.5, 'right' => 2, 'bottom' => 2.5, 'left' => 2]],
            ],
            'accessibility' => ['language' => 'es', 'title' => null, 'subject' => null, 'author' => 'CEEDCV'],
            'brand_name' => 'CEEDCV',
        ], $overrides);
    }

    private function render(array $theme, string $bodyHtml = '<p>Hola</p>'): string
    {
        return View::make('documents.render', [
            'document' => [
                'id' => 'doc-1',
                'title' => 'Mi documento',
                'subject' => 'Asunto',
                'lang' => 'es',
                'body_html' => $bodyHtml,
            ],
            'theme' => $theme,
        ])->render();
    }

    public function test_legacy_mode_renders_top_left_chrome_when_no_grid_blocks(): void
    {
        $html = $this->render($this->baseTheme());

        // Sin regions con `grid`: caemos al chrome estándar (@top-left/right + @bottom-center).
        $this->assertStringContainsString('@top-left', $html);
        $this->assertStringContainsString('@bottom-center', $html);
        // No emitimos el overlay.
        $this->assertStringNotContainsString('class="theme-overlay"', $html);
        // El header visual (sólo navegador) está presente.
        $this->assertStringContainsString('class="page-header"', $html);
    }

    public function test_legacy_margins_taken_from_theme_layout_page(): void
    {
        $html = $this->render($this->baseTheme([
            'layout' => ['page' => ['margin_cm' => ['top' => 3.1, 'right' => 1.5, 'bottom' => 3.2, 'left' => 1.7]]],
        ]));

        $this->assertStringContainsString('margin: 3.1000cm 1.5000cm 3.2000cm 1.7000cm', $html);
    }

    public function test_grid_mode_renders_overlay_with_blocks_and_excludes_top_left(): void
    {
        $theme = $this->baseTheme([
            'layout' => [
                'regions' => [
                    // content_slot define los márgenes del cuerpo.
                    ['id' => 'cs', 'type' => 'content_slot', 'grid' => ['x' => 1, 'y' => 4, 'w' => 10, 'h' => 44, 'z' => 1]],
                    // Texto en cabecera (col 0..6, fila 0..2).
                    ['id' => 't1', 'type' => 'text', 'grid' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 2, 'z' => 2],
                        'props' => ['text' => 'CEEDCV', 'size' => 10, 'color' => '#0b5394', 'align' => 'left']],
                    // Fecha en pie (col 0..3, fila 50..52).
                    ['id' => 'd1', 'type' => 'date', 'grid' => ['x' => 0, 'y' => 50, 'w' => 3, 'h' => 2, 'z' => 2],
                        'props' => ['format' => 'short', 'align' => 'left']],
                ],
            ],
        ]);

        $html = $this->render($theme);

        // El overlay se renderiza.
        $this->assertStringContainsString('class="theme-overlay"', $html);
        // El chrome @top-left NO se emite cuando hay grid layout.
        $this->assertStringNotContainsString('@top-left', $html);
        // Bloques renderizados.
        $this->assertStringContainsString('class="blk blk-text"', $html);
        $this->assertStringContainsString('CEEDCV', $html);
        // Aria-hidden en el overlay → marcado como artifact por WeasyPrint (PDF/UA).
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_grid_mode_margins_come_from_content_slot(): void
    {
        $theme = $this->baseTheme([
            'layout' => [
                'regions' => [
                    // content_slot a (x=2, y=6, w=8, h=40) sobre 12 cols × 52 rows.
                    // col_w = 21/12 = 1.75cm → margin-left = 2 * 1.75 = 3.5cm
                    //                          margin-right = (12-2-8) * 1.75 = 3.5cm
                    // row_h = 29.7/52 ≈ 0.5712cm → margin-top = 6 * 0.5712 ≈ 3.4269cm
                    //                              margin-bottom = (52-6-40) * 0.5712 ≈ 3.4269cm
                    ['id' => 'cs', 'type' => 'content_slot', 'grid' => ['x' => 2, 'y' => 6, 'w' => 8, 'h' => 40, 'z' => 1]],
                ],
            ],
        ]);

        $html = $this->render($theme);

        $this->assertStringContainsString('margin: 3.4269cm 3.5000cm 3.4269cm 3.5000cm', $html);
    }

    public function test_box_mode_margins_come_from_content_slot(): void
    {
        // Modelo nuevo: caja en mm absolutos (sin rejilla). A4 = 210×297 mm.
        $theme = $this->baseTheme([
            'layout' => [
                'regions' => [
                    ['id' => 'cs', 'type' => 'content_slot', 'box' => ['x' => 35, 'y' => 30, 'w' => 140, 'h' => 200, 'z' => 1]],
                ],
            ],
        ]);

        $html = $this->render($theme);

        // top = 30/10 = 3.0cm · left = 35/10 = 3.5cm
        // right = (210-35-140)/10 = 3.5cm · bottom = (297-30-200)/10 = 6.7cm
        $this->assertStringContainsString('margin: 3.0000cm 3.5000cm 6.7000cm 3.5000cm', $html);
    }

    public function test_box_mode_renders_overlay_block_geometry_in_cm(): void
    {
        $theme = $this->baseTheme([
            'layout' => [
                'regions' => [
                    ['id' => 't1', 'type' => 'text', 'box' => ['x' => 10, 'y' => 20, 'w' => 60, 'h' => 12, 'z' => 2],
                        'props' => ['text' => 'CEEDCV']],
                ],
            ],
        ]);

        $html = $this->render($theme);

        $this->assertStringContainsString('class="theme-overlay"', $html);
        // mm/10 → cm: left 1.0, top 2.0, width 6.0, height 1.2.
        $this->assertStringContainsString('left:1.0000cm;top:2.0000cm;width:6.0000cm;height:1.2000cm', $html);
        $this->assertStringContainsString('CEEDCV', $html);
    }

    public function test_grid_mode_falls_back_to_legacy_margins_when_no_content_slot(): void
    {
        $theme = $this->baseTheme([
            'layout' => [
                'page' => ['margin_cm' => ['top' => 2.5, 'right' => 2, 'bottom' => 2.5, 'left' => 2]],
                'regions' => [
                    // Sólo un texto, sin content_slot → mantenemos márgenes del theme.
                    ['id' => 't1', 'type' => 'text', 'grid' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 2, 'z' => 1],
                        'props' => ['text' => 'Hola']],
                ],
            ],
        ]);

        $html = $this->render($theme);

        // Margen del theme, no calculado.
        $this->assertStringContainsString('margin: 2.5000cm 2.0000cm 2.5000cm 2.0000cm', $html);
        // Y sigue siendo grid mode (overlay presente).
        $this->assertStringContainsString('class="theme-overlay"', $html);
    }

    public function test_body_html_is_inserted_unescaped(): void
    {
        $html = $this->render($this->baseTheme(), '<p>Texto <strong>bold</strong></p>');

        // El renderer ya escapa el contenido; aquí el Blade lo inserta tal cual.
        $this->assertStringContainsString('<p>Texto <strong>bold</strong></p>', $html);
    }

    public function test_scoped_themes_emit_per_theme_css_variables(): void
    {
        $html = View::make('documents.render', [
            'document' => [
                'id' => 'doc-1', 'title' => 'T', 'subject' => 'S', 'lang' => 'es',
                'body_html' => '<section class="doc-block doc-block--content" data-theme-id="theme-x"><p>Hola</p></section>',
            ],
            'theme' => $this->baseTheme(),
            'scoped_themes' => [
                ['id' => 'theme-x', 'palette' => ['primary' => '#ff0000'], 'typography' => ['heading_font' => 'Georgia']],
            ],
        ])->render();

        $this->assertStringContainsString('[data-theme-id="theme-x"]', $html);
        $this->assertStringContainsString('--color-primary: #ff0000', $html);
        $this->assertStringContainsString('--font-heading: Georgia', $html);
    }

    public function test_no_theme_block_gets_neutral_reset_and_named_page(): void
    {
        $html = $this->render(
            $this->baseTheme(),
            '<section class="doc-block doc-block--no-theme" data-theme-id="none"><p>Sin tema</p></section>',
        );

        $this->assertStringContainsString('[data-theme-id="none"]', $html);
        $this->assertStringContainsString('@page no-theme', $html);
        $this->assertStringContainsString('.doc-block--no-theme', $html);
        $this->assertStringContainsString('page: no-theme', $html);
    }

    public function test_toc_css_and_weasyprint_target_counter_present_in_pdf_mode(): void
    {
        // Sin preview_mode → modo PDF (WeasyPrint): debe emitir target-counter.
        $html = $this->render(
            $this->baseTheme(),
            '<nav class="doc-toc"><ul class="doc-toc__list"><li class="doc-toc__item doc-toc__item--h1">'
            .'<a class="doc-toc__link" href="#doc-toc-1"><span class="doc-toc__text">X</span>'
            .'<span class="doc-toc__page" data-target="#doc-toc-1"></span></a></li></ul></nav>',
        );

        $this->assertStringContainsString('.doc-toc__list', $html);
        $this->assertStringContainsString('target-counter(attr(href), page)', $html);
    }

    public function test_cover_block_css_and_named_page_present(): void
    {
        $html = $this->render(
            $this->baseTheme(),
            '<section class="doc-block doc-block--cover"><div class="cover-el cover-el--text" style="position:absolute;left:1cm;top:2cm;">Portada</div></section>',
        );

        $this->assertStringContainsString('@page cover', $html);
        $this->assertStringContainsString('.doc-block--cover', $html);
        $this->assertStringContainsString('page: cover', $html);
        // Counter del nº de página de portada.
        $this->assertStringContainsString('.cover-pn::before { content: counter(page); }', $html);
    }

    public function test_invalid_text_align_is_normalized(): void
    {
        $theme = $this->baseTheme([
            'layout' => [
                'regions' => [
                    ['id' => 'cs', 'type' => 'content_slot', 'grid' => ['x' => 0, 'y' => 4, 'w' => 12, 'h' => 44]],
                    ['id' => 't', 'type' => 'text', 'grid' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2],
                        'props' => ['text' => 'X', 'align' => 'evil-value']],
                ],
            ],
        ]);

        $html = $this->render($theme);

        // El align inválido debe caer a 'left' (default).
        $this->assertStringContainsString('text-align:left', $html);
        $this->assertStringNotContainsString('evil-value', $html);
    }
}
