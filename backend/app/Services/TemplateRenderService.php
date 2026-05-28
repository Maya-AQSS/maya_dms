<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\DocumentConstants;
use App\Models\Theme;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\TemplateRenderServiceInterface;
use App\Support\BlockNoteHtmlRenderer;
use Illuminate\Support\Facades\View;

/**
 * Genera el HTML themed de una plantilla para mostrar cómo se verá un
 * documento creado a partir de ella. Reusa el mismo Blade `documents.render`
 * para que la fidelidad sea total: misma paleta, tipografía, layout y CSS
 * Paged Media — el único cambio es que el cuerpo se construye a partir de
 * los `default_content` de los `template_blocks` (no de un documento real).
 */
class TemplateRenderService implements TemplateRenderServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

    public function renderHtml(string $templateId, bool $previewMode = false): string
    {
        $template = $this->templateRepository->findOrFailWithBlocksOrderedWithoutCatalogScope($templateId);

        $theme = $this->resolveTheme($template);

        // El cuerpo del "preview document" se construye a partir de los
        // `default_content` de cada bloque, en orden. Cada bloque empieza
        // con su título como h2 para que el árbol PDF/UA quede correcto
        // si en algún momento alguien decide imprimir el preview.
        $blockHtmlParts = [];
        foreach ($template->blocks as $block) {
            $title = (string) ($block->title ?? '');
            if ($title !== '') {
                $blockHtmlParts[] = '<h2>'.e($title).'</h2>';
            }
            $default = $block->default_content;
            if (is_array($default) && count($default) > 0) {
                $blockHtmlParts[] = BlockNoteHtmlRenderer::renderBlocks($default);
            } elseif (is_string($default) && $default !== '') {
                // Backwards-compat: algún seed legacy guardaba string en lugar de array.
                $blockHtmlParts[] = '<p>'.e($default).'</p>';
            } else {
                $blockHtmlParts[] = '<p><em>—</em></p>';
            }
        }
        $body = implode("\n", $blockHtmlParts);

        return View::make('documents.render', [
            'document' => [
                'id' => (string) $template->id,
                'title' => (string) ($template->name ?? 'Plantilla'),
                'subject' => $template->description,
                'lang' => $theme['accessibility']['language'] ?? 'es',
                'body_html' => $body,
            ],
            'theme' => $theme,
            'preview_mode' => $previewMode,
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTheme(Template $template): array
    {
        /** @var Theme|null $theme */
        $theme = $template->theme ?? null;

        if ($theme === null) {
            return DocumentConstants::DEFAULT_THEME;
        }

        return [
            'palette' => (array) ($theme->palette ?? DocumentConstants::DEFAULT_THEME['palette']),
            'typography' => (array) ($theme->typography ?? DocumentConstants::DEFAULT_THEME['typography']),
            'layout' => (array) ($theme->layout ?? DocumentConstants::DEFAULT_THEME['layout']),
            'assets' => (array) ($theme->assets ?? DocumentConstants::DEFAULT_THEME['assets']),
            'accessibility' => (array) ($theme->accessibility ?? DocumentConstants::DEFAULT_THEME['accessibility']),
            'brand_name' => (string) ($theme->name ?? 'CEEDCV'),
        ];
    }
}
