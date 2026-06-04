<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\DocumentConstants;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Contracts\TemplateRenderServiceInterface;
use Illuminate\Support\Facades\View;
use Maya\Editor\Renderers\TiptapHtmlRenderer;
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
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly ThemeRepositoryInterface $themeRepository,
    ) {}

    public function renderHtml(string $templateId, bool $previewMode = false): string
    {
        $template = $this->templateRepository->findForRenderingWithoutCatalogScope($templateId);
        if ($template === null) {
            throw new NotFoundHttpException('Template no encontrado.');
        }

        $theme = $this->resolveTheme($template->themeId);

        // El cuerpo del "preview document" se construye a partir de los
        // `default_content` de cada bloque, en orden. Cada bloque empieza
        // con su título como h2 para que el árbol PDF/UA quede correcto
        // si en algún momento alguien decide imprimir el preview.
        $blockHtmlParts = [];
        foreach ($template->blocks as $block) {
            $title = (string) ($block['title'] ?? '');
            if ($title !== '') {
                $blockHtmlParts[] = '<h2>'.e($title).'</h2>';
            }
            $default = $block['default_content'];
            if (is_array($default) && count($default) > 0) {
                $blockHtmlParts[] = TiptapHtmlRenderer::renderDoc($default);
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
            'assets' => $themeDto->assets ?: DocumentConstants::DEFAULT_THEME['assets'],
            'accessibility' => $themeDto->accessibility ?: DocumentConstants::DEFAULT_THEME['accessibility'],
            'brand_name' => $themeDto->brandName,
        ];
    }
}
