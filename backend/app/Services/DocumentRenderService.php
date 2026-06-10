<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\DocumentConstants;
use App\Enums\BlockType;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Concerns\BlockRenderSupport;
use App\Services\Contracts\DocumentRenderServiceInterface;
use Illuminate\Support\Facades\View;

/**
 * Resuelve theme + bloques de un documento y produce HTML themed. El mismo
 * HTML alimenta el preview de navegador y el export PDF (Phase 4 vía WeasyPrint).
 *
 * Nota: Acepta Document modelo (ya cargado via repository), pero extrae
 * toda lógica de transformación de datos dentro de este service.
 */
class DocumentRenderService implements DocumentRenderServiceInterface
{
    use BlockRenderSupport;

    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly TocBuilderService $tocBuilder,
        private readonly CoverRenderService $coverRenderer,
        private readonly ThemeRepositoryInterface $themeRepository,
    ) {}

    protected function themeRepository(): ThemeRepositoryInterface
    {
        return $this->themeRepository;
    }

    public function renderHtml(string $documentId, bool $previewMode = false): string
    {
        $document = $this->documentRepository->findWithBlocksAndThemeOrFail($documentId);

        return $this->assembleHtml($document, $previewMode);
    }

    /**
     * Renderiza el HTML de una versión histórica del documento a partir del
     * contenido CONGELADO en su snapshot, manteniendo tema + geometría
     * estructural de la plantilla VIVA (igual que hace el frontend al previsualizar
     * una versión). Se carga el documento vivo y se sobrescribe el `content` de
     * cada bloque con el del snapshot (emparejando por `template_block_id`); los
     * bloques sin entrada en el snapshot caen a su `default_content`, como hoy.
     *
     * @param  list<array<string, mixed>>  $snapshotBlocks  Bloques resueltos del snapshot
     *                                                       (con `template_block_id` y `content`).
     */
    public function renderHtmlForVersion(string $documentId, array $snapshotBlocks, bool $previewMode = false): string
    {
        $document = $this->documentRepository->findWithBlocksAndThemeOrFail($documentId);

        $frozenContentByTemplateBlockId = [];
        foreach ($snapshotBlocks as $snapBlock) {
            if (! is_array($snapBlock)) {
                continue;
            }
            $tplBlockId = isset($snapBlock['template_block_id']) ? (string) $snapBlock['template_block_id'] : '';
            if ($tplBlockId === '' || ! array_key_exists('content', $snapBlock)) {
                continue;
            }
            $frozenContentByTemplateBlockId[$tplBlockId] = $snapBlock['content'];
        }

        foreach ($document->blocks as $block) {
            $tplBlockId = (string) ($block->template_block_id ?? '');
            if ($tplBlockId !== '' && array_key_exists($tplBlockId, $frozenContentByTemplateBlockId)) {
                $block->content = $frozenContentByTemplateBlockId[$tplBlockId];
            }
        }

        return $this->assembleHtml($document, $previewMode);
    }

    /**
     * Ensambla el HTML themed final a partir de un Document ya cargado (con
     * bloques + tema). Compartido por {@see renderHtml} (HEAD vivo) y
     * {@see renderHtmlForVersion} (snapshot congelado).
     */
    private function assembleHtml(Document $document, bool $previewMode = false): string
    {
        $theme = $this->extractThemeData($document);
        $defaultThemeId = $document->template?->theme_id !== null ? (string) $document->template->theme_id : '';

        // Temas distintos del por-defecto referenciados por bloques (override por bloque).
        $scopedThemes = $this->resolveScopedBlockThemes(
            $document->blocks->map(fn ($block) => [
                'theme_id' => $block->templateBlock?->theme_id !== null ? (string) $block->templateBlock->theme_id : null,
                'apply_theme' => (bool) ($block->templateBlock?->apply_theme ?? true),
            ])->all(),
            $defaultThemeId,
        );

        $blockHtmlParts = $this->renderBlockHtmlParts($document, $defaultThemeId, $previewMode, $theme['accessibility']['language'] ?? 'es');
        $body = $this->tocBuilder->build(implode("\n", $blockHtmlParts), $this->blocksMeta($document));

        return View::make('documents.render', [
            'document' => [
                'id' => (string) $document->id,
                'title' => (string) ($document->title ?? 'Document title not found'),
                'subject' => $document->template?->description ?? 'Description not found',
                'lang' => $theme['accessibility']['language'] ?? 'es',
                'body_html' => $body,
            ],
            'theme' => $theme,
            'scoped_themes' => $scopedThemes,
            'preview_mode' => $previewMode,
        ])->render();
    }

    /**
     * Extrae el mapa de valores de placeholders del `content` de un bloque de
     * documento (formato { kind:'cover-fill', values:{key:texto} }).
     *
     * @return array<string, string>
     */
    private function coverValues(mixed $content): array
    {
        if (! is_array($content)) {
            return [];
        }
        $values = $content['values'] ?? null;
        if (! is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * Extracts theme data from document model into scalar/array form.
     *
     * @return array<string, mixed>
     */
    private function extractThemeData(Document $document): array
    {
        $themeModel = $document->template?->theme ?? null;

        if ($themeModel === null) {
            return DocumentConstants::DEFAULT_THEME;
        }

        return [
            'palette' => (array) ($themeModel->palette ?? DocumentConstants::DEFAULT_THEME['palette']),
            'typography' => (array) ($themeModel->typography ?? DocumentConstants::DEFAULT_THEME['typography']),
            'layout' => (array) ($themeModel->layout ?? DocumentConstants::DEFAULT_THEME['layout']),
            'accessibility' => (array) ($themeModel->accessibility ?? DocumentConstants::DEFAULT_THEME['accessibility']),
            'brand_name' => (string) ($themeModel->name ?? 'CEEDCV'),
        ];
    }

    /**
     * Renders block HTML parts from document blocks.
     *
     * Cada bloque se envuelve en un `<section>` con `data-block-id`/`data-block-type`
     * para poder aplicar reglas de maquetación (salto de página, y en fases
     * posteriores tema por bloque). `page_break_after` fuerza que el bloque
     * siguiente empiece en una página nueva en el PDF.
     *
     * @return list<string>
     */
    private function renderBlockHtmlParts(Document $document, string $defaultThemeId = '', bool $previewMode = false, string $lang = 'es'): array
    {
        $blockHtmlParts = [];
        foreach ($document->blocks as $block) {
            $tpl = $block->templateBlock;
            $type = $tpl?->block_type instanceof BlockType
                ? $tpl->block_type->value
                : (string) ($tpl?->block_type ?? 'content');
            $pageBreakAfter = (bool) ($tpl?->page_break_after ?? false);
            $applyTheme = (bool) ($tpl?->apply_theme ?? true);
            $themeId = $this->effectiveThemeId(
                $tpl?->theme_id !== null ? (string) $tpl->theme_id : null,
                $applyTheme,
                $defaultThemeId,
            );

            $inner = '';
            if ($type === 'cover') {
                // Portada: geometría del template block + valores del documento.
                $geometry = is_array($tpl?->default_content) ? $tpl->default_content : [];
                $values = $this->coverValues($block->content);
                $inner = $this->coverRenderer->renderInner($geometry, $values, $previewMode, $lang);
            } elseif ($type === 'index') {
                // Bloque índice: sólo el título; el TOC lo inyecta TocBuilderService.
                $title = (string) ($tpl?->title ?? '');
                if ($title !== '') {
                    $inner .= '<h2>'.e($title).'</h2>';
                }
            } elseif ($type !== 'blank') {
                // El título del bloque NO se imprime en el PDF: es metadato (el
                // nombre que ve el redactor), y el contenido ya trae sus propios
                // encabezados. Imprimirlo duplicaba cada cabecera. El índice se
                // construye con los encabezados internos del contenido.
                //
                // El redactor puede dejar el bloque sin rellenar (content vacío):
                // se cae al contenido por defecto de la plantilla, igual que el
                // preview de edición (`blockContentForPreview`).
                $default = $block->content;
                if (! is_array($default) || count($default) === 0) {
                    $default = is_array($tpl?->default_content) ? $tpl->default_content : $default;
                }
                if (is_array($default) && count($default) > 0) {
                    $inner .= $this->renderTiptapContent($default);
                } elseif (is_string($default) && $default !== '') {
                    // Backwards-compat: algún seed legacy guardaba string en lugar de array.
                    $inner .= '<p>'.e($default).'</p>';
                } else {
                    $inner .= '<p><em>—</em></p>';
                }
            }

            $classes = $this->blockSectionClasses($type, $pageBreakAfter, $applyTheme);

            // Ancla estable para el índice: usa el id del bloque de PLANTILLA
            // (el índice referencia bloques de plantilla; coincide con el preview
            // de plantilla y con la config guardada).
            $anchorId = $tpl?->id !== null ? (string) $tpl->id : (string) $block->id;

            $blockHtmlParts[] = $this->wrapBlockSection(
                $classes,
                $anchorId,
                (string) $block->id,
                $type,
                $themeId,
                $pageBreakAfter,
                $inner,
            );
        }

        return $blockHtmlParts;
    }

    /**
     * Metadata ordenada de bloques para el índice (TocBuilderService).
     *
     * @return list<array{id: string, title: string, block_type: string, index_config: array<string,mixed>|null}>
     */
    private function blocksMeta(Document $document): array
    {
        $meta = [];
        foreach ($document->blocks as $block) {
            $tpl = $block->templateBlock;
            $type = $tpl?->block_type instanceof BlockType
                ? $tpl->block_type->value
                : (string) ($tpl?->block_type ?? 'content');
            $meta[] = [
                'id' => $tpl?->id !== null ? (string) $tpl->id : (string) $block->id,
                'title' => (string) ($tpl?->title ?? ''),
                'block_type' => $type,
                // El índice usa la config guardada en el DOCUMENTO (selección del
                // redactor) si existe; si no, la config por defecto de la plantilla.
                'index_config' => $type === 'index'
                    ? (is_array($block->content)
                        ? $block->content
                        : (is_array($tpl?->default_content) ? $tpl->default_content : null))
                    : null,
            ];
        }

        return $meta;
    }
}
