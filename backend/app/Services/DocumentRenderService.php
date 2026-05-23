<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\Theme;
use App\Services\Contracts\DocumentRenderServiceInterface;
use App\Support\BlockNoteHtmlRenderer;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resuelve theme + bloques de un documento y produce HTML themed. El mismo
 * HTML alimenta el preview de navegador y el export PDF (Phase 4 vía WeasyPrint).
 */
class DocumentRenderService implements DocumentRenderServiceInterface
{
    /**
     * Theme por defecto cuando el documento (o su plantilla) no tiene theme
     * asignado. Mismos valores que los defaults del StoreThemeRequest para
     * consistencia visual.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_THEME = [
        'palette' => [
            'primary' => '#0b5394',
            'secondary' => '#666666',
            'text' => '#1a1a1a',
            'background' => '#ffffff',
            'accent' => '#f59e0b',
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
            'language' => 'es',
            'title' => null,
            'subject' => null,
            'author' => 'CEEDCV',
        ],
        'brand_name' => 'CEEDCV',
    ];

    public function renderHtml(string $documentId, bool $previewMode = false): string
    {
        /** @var Document|null $document */
        $document = Document::query()
            ->with(['blocks' => fn ($q) => $q->orderBy('sort_order'), 'template.theme'])
            ->find($documentId);

        if ($document === null) {
            throw new NotFoundHttpException('Documento no encontrado.');
        }

        $theme = $this->resolveTheme($document);
        $blocksWithKind = $document->blocks->map(function ($block): array {
            $kind = $block->kind;
            $kindValue = $kind instanceof \BackedEnum
                ? $kind->value
                : (is_string($kind) && $kind !== '' ? $kind : 'content');

            return [
                'kind' => $kindValue,
                'content' => (array) $block->content,
            ];
        })->all();

        $body = BlockNoteHtmlRenderer::renderDocument($blocksWithKind);

        // Si el documento empieza con un bloque cover, la portada reemplaza
        // al título y subject de la cabecera del <main>.
        $firstKind = isset($blocksWithKind[0]['kind']) ? (string) $blocksWithKind[0]['kind'] : 'content';
        $suppressDocTitle = $firstKind === 'cover';

        return View::make('documents.render', [
            'document' => [
                'id' => (string) $document->id,
                'title' => (string) ($document->name ?? 'Documento'),
                'subject' => $document->description,
                'lang' => $theme['accessibility']['language'] ?? 'es',
                'body_html' => $body,
                'suppress_doc_title' => $suppressDocTitle,
            ],
            'theme' => $theme,
            'preview_mode' => $previewMode,
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTheme(Document $document): array
    {
        /** @var Theme|null $theme */
        $theme = $document->template?->theme ?? null;

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
