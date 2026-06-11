<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\DocumentConstants;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Concerns\BlockRenderSupport;
use App\Services\Contracts\TemplateRenderServiceInterface;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Genera el HTML themed de una plantilla para mostrar cómo se verá un
 * documento creado a partir de ella. Reusa el mismo Blade `documents.render`
 * para que la fidelidad sea total: misma paleta, tipografía, layout y CSS
 * Paged Media — el único cambio es que el cuerpo se construye a partir de
 * los `default_content` de los `template_blocks` (no de un documento real).
 */
class TemplateRenderService implements TemplateRenderServiceInterface
{
    use BlockRenderSupport;

    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly ThemeRepositoryInterface $themeRepository,
        private readonly TocBuilderService $tocBuilder,
        private readonly CoverRenderService $coverRenderer,
        private readonly TemplateVersionBlockLayerResolver $blockLayerResolver,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    protected function themeRepository(): ThemeRepositoryInterface
    {
        return $this->themeRepository;
    }

    public function renderHtml(string $templateId, bool $previewMode = false): string
    {
        $template = $this->templateRepository->findForRenderingWithoutCatalogScope($templateId);
        if ($template === null) {
            throw new NotFoundHttpException('Template no encontrado.');
        }

        $theme = $this->resolveTheme($template->themeId);
        $defaultThemeId = $template->themeId !== null ? (string) $template->themeId : '';

        // Temas distintos del por-defecto referenciados por bloques (override por bloque).
        $scopedThemes = $this->resolveScopedBlockThemes(
            array_map(fn ($block) => [
                'theme_id' => isset($block['theme_id']) && $block['theme_id'] !== null ? (string) $block['theme_id'] : null,
                'apply_theme' => (bool) ($block['apply_theme'] ?? true),
            ], $template->blocks),
            $defaultThemeId,
        );

        // El cuerpo del "preview document" se construye a partir de los
        // `default_content` de cada bloque, en orden. Cada bloque empieza
        // con su título como h2 para que el árbol PDF/UA quede correcto
        // si en algún momento alguien decide imprimir el preview.
        $body = $this->renderBlocksToHtmlBody($template->blocks, $defaultThemeId, $theme, $previewMode);

        return View::make('documents.render', [
            'document' => [
                'id' => (string) $template->id,
                'title' => (string) ($template->name ?? 'Plantilla'),
                'subject' => $template->description,
                'lang' => $theme['accessibility']['language'] ?? 'es',
                'body_html' => $body,
            ],
            'theme' => $theme,
            'scoped_themes' => $scopedThemes,
            'preview_mode' => $previewMode,
        ])->render();
    }

    public function renderHtmlForVersion(string $templateId, string $versionId, bool $previewMode = false): string
    {
        $template = $this->templateRepository->findForRenderingWithoutCatalogScope($templateId);
        if ($template === null) {
            throw new NotFoundHttpException('Template no encontrado.');
        }

        // Validate that the version belongs to this template (type + entity id match).
        $entityVersion = $this->entityVersionRepository->findPublishedByIdAndType($versionId, Template::class);
        if ($entityVersion === null || (string) $entityVersion->versionable_id !== $templateId) {
            throw new NotFoundHttpException('Versión no encontrada para esta plantilla.');
        }

        // Reconstruct the frozen block list from the snapshot layers.
        $snapshotBlocks = $this->blockLayerResolver->resolveBlocksSnapshot($versionId);

        // Build a map of frozen default_content by block id for quick lookup.
        $frozenByBlockId = [];
        foreach ($snapshotBlocks as $snapBlock) {
            if (is_array($snapBlock) && isset($snapBlock['id'])) {
                $frozenByBlockId[(string) $snapBlock['id']] = $snapBlock['default_content'] ?? null;
            }
        }

        // Merge: start from the LIVE template blocks (ordered), override default_content
        // for blocks present in the snapshot. Blocks absent from the snapshot keep their
        // live default_content (mirroring DocumentRenderService::renderHtmlForVersion).
        $mergedBlocks = [];
        foreach ($template->blocks as $block) {
            $blockId = (string) ($block['id'] ?? '');
            if ($blockId !== '' && array_key_exists($blockId, $frozenByBlockId)) {
                $block['default_content'] = $frozenByBlockId[$blockId];
            }
            $mergedBlocks[] = $block;
        }

        // Use the shared renderHtml pipeline but with the merged blocks injected.
        return $this->renderBlocksToHtml($template, $mergedBlocks, $previewMode);
    }

    /**
     * Renderiza un conjunto de bloques (con `default_content` ya resuelto) usando
     * el mismo pipeline que renderHtml. Extraído para compartirlo con renderHtmlForVersion.
     *
     * @param  object  $template  TemplateDto-like (themeId, name, description, blocks…)
     * @param  list<array<string, mixed>>  $blocks
     */
    private function renderBlocksToHtml(object $template, array $blocks, bool $previewMode): string
    {
        $theme = $this->resolveTheme($template->themeId);
        $defaultThemeId = $template->themeId !== null ? (string) $template->themeId : '';

        $scopedThemes = $this->resolveScopedBlockThemes(
            array_map(fn ($block) => [
                'theme_id' => isset($block['theme_id']) && $block['theme_id'] !== null ? (string) $block['theme_id'] : null,
                'apply_theme' => (bool) ($block['apply_theme'] ?? true),
            ], $blocks),
            $defaultThemeId,
        );

        $body = $this->renderBlocksToHtmlBody($blocks, $defaultThemeId, $theme, $previewMode);

        return View::make('documents.render', [
            'document' => [
                'id' => (string) $template->id,
                'title' => (string) ($template->name ?? 'Plantilla'),
                'subject' => $template->description,
                'lang' => $theme['accessibility']['language'] ?? 'es',
                'body_html' => $body,
            ],
            'theme' => $theme,
            'scoped_themes' => $scopedThemes,
            'preview_mode' => $previewMode,
        ])->render();
    }

    /**
     * Construye el cuerpo HTML (con índice) de una lista de bloques de plantilla.
     * Compartido por {@see renderHtml} y {@see renderBlocksToHtml}. Aplica la misma
     * numeración de página que el render de documento (preliminares sin número +
     * reinicio en el bloque de inicio) para que el preview de plantilla coincida
     * con el PDF final.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $theme
     */
    private function renderBlocksToHtmlBody(array $blocks, string $defaultThemeId, array $theme, bool $previewMode): string
    {
        $startIndex = $this->resolveNumberingStartIndex(
            array_map(fn ($block): array => [
                'block_type' => (string) ($block['block_type'] ?? 'content'),
                'page_number_start' => (bool) ($block['page_number_start'] ?? false),
            ], $blocks),
        );

        $blockHtmlParts = [];
        foreach (array_values($blocks) as $idx => $block) {
            $type = (string) ($block['block_type'] ?? 'content');
            $pageBreakAfter = (bool) ($block['page_break_after'] ?? false);
            $applyTheme = (bool) ($block['apply_theme'] ?? true);
            $themeId = $this->effectiveThemeId(
                isset($block['theme_id']) && $block['theme_id'] !== null ? (string) $block['theme_id'] : null,
                $applyTheme,
                $defaultThemeId,
            );

            $isPageStart = $startIndex !== null && $idx === $startIndex;
            $isUnnumbered = ($startIndex === null || $idx < $startIndex) && $type !== 'cover';

            $inner = '';
            if ($type === 'cover') {
                // Portada en preview de plantilla: geometría del default_content,
                // sin valores (los placeholders muestran su defaultText).
                $geometry = is_array($block['default_content'] ?? null) ? $block['default_content'] : [];
                $inner = $this->coverRenderer->renderInner($geometry, [], $previewMode, $theme['accessibility']['language'] ?? 'es');
            } elseif ($type === 'index') {
                // Bloque índice: sólo el título; el TOC lo inyecta TocBuilderService.
                $title = (string) ($block['title'] ?? '');
                if ($title !== '') {
                    $inner .= '<h2>'.e($title).'</h2>';
                }
            } elseif ($type !== 'blank') {
                // El título del bloque NO se imprime (metadato): el contenido ya
                // trae sus propios encabezados; imprimirlo duplicaba cada cabecera.
                $default = $block['default_content'];
                if (is_array($default) && count($default) > 0) {
                    $inner .= $this->renderTiptapContent($default);
                } elseif (is_string($default) && $default !== '') {
                    // Backwards-compat: algún seed legacy guardaba string en lugar de array.
                    $inner .= '<p>'.e($default).'</p>';
                } else {
                    $inner .= '<p><em>—</em></p>';
                }
            }

            $classes = $this->blockSectionClasses($type, $pageBreakAfter, $applyTheme, $isUnnumbered, $isPageStart);
            $anchorId = (string) ($block['id'] ?? '');

            $blockHtmlParts[] = $this->wrapBlockSection(
                $classes,
                $anchorId,
                $anchorId,
                $type,
                $themeId,
                $pageBreakAfter,
                $inner,
            );
        }

        return $this->tocBuilder->build(implode("\n", $blockHtmlParts), $this->blocksMeta($blocks));
    }

    /**
     * Metadata ordenada de bloques para el índice (TocBuilderService).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array{id: string, title: string, block_type: string, index_config: array<string,mixed>|null}>
     */
    private function blocksMeta(array $blocks): array
    {
        $meta = [];
        foreach ($blocks as $block) {
            $type = (string) ($block['block_type'] ?? 'content');
            $meta[] = [
                'id' => (string) ($block['id'] ?? ''),
                'title' => (string) ($block['title'] ?? ''),
                'block_type' => $type,
                'index_config' => ($type === 'index' && is_array($block['default_content'] ?? null)) ? $block['default_content'] : null,
            ];
        }

        return $meta;
    }

    /**
     * Resolve theme data by ID or return defaults.
     *
     * @return array<string, mixed>
     */
    private function resolveTheme(?string $themeId): array
    {
        if ($themeId === null) {
            return DocumentConstants::DEFAULT_THEME;
        }

        $themeDto = $this->themeRepository->findThemeResolvedById($themeId);
        if ($themeDto === null) {
            return DocumentConstants::DEFAULT_THEME;
        }

        return [
            'palette' => $themeDto->palette ?: DocumentConstants::DEFAULT_THEME['palette'],
            'typography' => $themeDto->typography ?: DocumentConstants::DEFAULT_THEME['typography'],
            'layout' => $themeDto->layout ?: DocumentConstants::DEFAULT_THEME['layout'],
            'accessibility' => $themeDto->accessibility ?: DocumentConstants::DEFAULT_THEME['accessibility'],
            'brand_name' => $themeDto->brandName,
        ];
    }
}
