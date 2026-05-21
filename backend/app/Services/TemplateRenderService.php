<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Template;
use App\Models\Theme;
use App\Services\Contracts\TemplateRenderServiceInterface;
use App\Support\BlockNoteHtmlRenderer;
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
    /**
     * Theme por defecto cuando la plantilla no tiene theme asignado. Mismo
     * shape que `DocumentRenderService::DEFAULT_THEME` para que el Blade
     * funcione sin ramas adicionales.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_THEME = [
        'palette' => [
            'primary' => '#0b5394', 'secondary' => '#666666', 'text' => '#1a1a1a',
            'background' => '#ffffff', 'accent' => '#f59e0b',
        ],
        'typography' => [
            'heading_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
            'body_font' => 'DejaVu Sans, Liberation Sans, sans-serif',
            'base_size_pt' => 11,
            'line_height' => 1.5,
        ],
        'layout' => [
            'regions' => [],
            'page' => ['size' => 'A4', 'margin_cm' => ['top' => 2.5, 'right' => 2, 'bottom' => 2.5, 'left' => 2]],
        ],
        'assets' => [
            'logo_path' => null,
            'background_image_path' => null,
            'watermark_path' => null,
        ],
        'accessibility' => [
            'language' => 'es', 'title' => null, 'subject' => null, 'author' => 'CEEDCV',
        ],
        'brand_name' => 'CEEDCV',
    ];

    public function renderHtml(string $templateId, bool $previewMode = false): string
    {
        /** @var Template|null $template */
        $template = Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->with(['blocks' => fn ($q) => $q->orderBy('sort_order'), 'theme'])
            ->find($templateId);

        if ($template === null) {
            throw new NotFoundHttpException('Plantilla no encontrada.');
        }

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
            return self::DEFAULT_THEME;
        }

        return [
            'palette' => (array) ($theme->palette ?? self::DEFAULT_THEME['palette']),
            'typography' => (array) ($theme->typography ?? self::DEFAULT_THEME['typography']),
            'layout' => (array) ($theme->layout ?? self::DEFAULT_THEME['layout']),
            'assets' => (array) ($theme->assets ?? self::DEFAULT_THEME['assets']),
            'accessibility' => (array) ($theme->accessibility ?? self::DEFAULT_THEME['accessibility']),
            'brand_name' => (string) ($theme->name ?? 'CEEDCV'),
        ];
    }
}
