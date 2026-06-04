<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\DocumentRepositoryInterface;
use Maya\Editor\Renderers\TiptapHtmlRenderer;
use Maya\Editor\Support\DocxExporter;

/**
 * Handles DOCX export: renders document blocks from TipTap JSON to HTML and packages as .docx.
 *
 * The export logic (JSON decode, TipTap validation, HTML rendering) is encapsulated here,
 * allowing the controller to remain thin and focused on HTTP concerns.
 */
class DocumentDocxExportService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    /**
     * Exports a document's blocks as HTML-based .docx file.
     *
     * @return array<string, mixed> Array with keys: 'bin', 'filename'
     *   - bin: binary content of the .docx file
     *   - filename: suggested filename for download
     */
    public function exportToDocx(string $documentId): array
    {
        $document = $this->documentRepository->findByIdWithAccessControl($documentId);
        $headBlocks = $this->documentRepository->findBlocksForExport($documentId);

        $htmlParts = [];
        foreach ($headBlocks as $block) {
            // document_blocks only stores `content` (cast to array); there is no
            // `default_content` here — that column lives on template_blocks.
            $payload = $block->content;
            if ($payload === null) {
                continue;
            }
            $doc = is_string($payload) ? json_decode($payload, true) : (array) $payload;
            if (! is_array($doc)) {
                continue;
            }
            // Los bloques se persisten como documento TipTap (`{type:doc}`);
            // el render produce el HTML que empaqueta el DOCX.
            $htmlParts[] = TiptapHtmlRenderer::renderDoc($doc);
        }

        $html = implode("\n", array_filter($htmlParts));
        $bin = DocxExporter::export($html, $document->title ?? 'Document');
        $filename = ($document->title ?? 'document').'.docx';

        return [
            'bin' => $bin,
            'filename' => $filename,
        ];
    }
}
