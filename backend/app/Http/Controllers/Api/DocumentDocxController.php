<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Maya\Editor\Renderers\TiptapHtmlRenderer;
use Maya\Editor\Support\DocxExporter;
use Symfony\Component\HttpFoundation\Response;

/**
 * .docx export.
 *
 * Import is intentionally client-side: the wizard's DocxBlockSplitter reads the
 * uploaded .docx in the browser (mammoth.js → HTML → blocks) and creates blocks
 * via the existing block API, so no server-side import endpoint is needed.
 *
 * Export renders the document's blocks (TipTap JSON) to HTML and packages it as
 * a .docx. Authorization is enforced via the `view` policy on the target document.
 */
final class DocumentDocxController extends Controller
{
    public function export(string $document): Response
    {
        // Resolve explicitly with a qualified key instead of implicit route-model
        // binding: the Document model applies global scopes that JOIN entity_versions,
        // which makes an unqualified `where id = ?` ambiguous. whereKey() qualifies to
        // `documents.id`. The `user_access` global scope still filters out documents the
        // caller may not see (→ 404), and the policy gate below covers found-but-denied.
        $document = Document::query()->whereKey($document)->firstOrFail();

        $this->authorize('view', $document);

        $headBlocks = $document->blocks()
            ->orderBy('sort_order')
            ->get(['content', 'sort_order']);

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
            // Already a TipTap doc; if it's still legacy BlockNote, the
            // migration command has not run yet — caller can run
            // `php artisan blocknote:migrate-to-tiptap` first.
            $htmlParts[] = TiptapHtmlRenderer::renderDoc($doc);
        }

        $html = implode("\n", array_filter($htmlParts));
        $bin = DocxExporter::export($html, $document->title ?? 'Document');

        $filename = ($document->title ?? 'document').'.docx';

        return response($bin, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.addslashes($filename).'"',
        ]);
    }
}
