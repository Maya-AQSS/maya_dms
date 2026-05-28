<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\DocumentConstants;
use App\Models\Theme;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentRenderServiceInterface;
use App\Support\BlockNoteHtmlRenderer;
use Illuminate\Support\Facades\View;

/**
 * Resuelve theme + bloques de un documento y produce HTML themed. El mismo
 * HTML alimenta el preview de navegador y el export PDF (Phase 4 vía WeasyPrint).
 */
class DocumentRenderService implements DocumentRenderServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function renderHtml(string $documentId, bool $previewMode = false): string
    {
        $document = $this->documentRepository->findWithBlocksAndThemeOrFail($documentId);

        $theme = $this->resolveTheme($document);
        $blockHtmlParts = [];
            foreach ($document->blocks as $block) {
                $title = (string) ($block->templateBlock?->title ?? '');
                if ($title !== '') {
                    $blockHtmlParts[] = '<h2>'.e($title).'</h2>';
                }
                $default = $block->content;
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
                'id' => (string) $document->id,
                'title' => (string) ($document->title ?? 'Document title not found'),
                'subject' => $document->template?->description ?? 'Description not found',
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
    private function resolveTheme(\App\Models\Document $document): array
    {
        /** @var Theme|null $theme */
        $theme = $document->template?->theme ?? null;

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
