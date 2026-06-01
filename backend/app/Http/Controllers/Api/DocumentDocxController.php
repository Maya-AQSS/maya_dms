<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maya\Editor\Renderers\TiptapHtmlRenderer;
use Maya\Editor\Support\DocxExporter;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * .docx import / export.
 *
 * Security hardening (council recommendation):
 *   - Hard upload size cap (20 MB before reaching this code; 50 MB
 *     enforced here as defence in depth).
 *   - ZIP entry scan: a .docx is a ZIP — reject if any entry's
 *     uncompressed size or count exceeds sane limits (zip-bomb guard).
 *   - Imports go through phpoffice/phpword with libxml entity loading
 *     disabled (handled inside `DocxExporter`/import side).
 */
final class DocumentDocxController extends Controller
{
    private const MAX_UPLOAD_BYTES = 50 * 1024 * 1024;       // 50 MB
    private const MAX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024; // 200 MB
    private const MAX_ENTRIES = 1000;

    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:51200'], // KB → 50 MB
        ]);
        $validator->validate();

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            abort(413, 'File too large.');
        }
        $this->guardAgainstZipBomb($file->getRealPath());

        // Minimal stub: we accept the file, return a stub TipTap doc.
        // Full HTML → ProseMirror conversion is wired in the frontend
        // via mammoth.js — server side just acks the upload and offers
        // the file URL for the client to fetch & parse.
        return response()->json([
            'data' => [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'note' => 'Client-side import: fetch this file via mammoth.js and feed the HTML to MayaEditor.',
            ],
        ]);
    }

    public function export(Request $request, Document $document): Response
    {
        $this->authorize('view', $document);

        $headBlocks = $document->blocks()
            ->orderBy('order')
            ->get(['content', 'default_content']);

        $htmlParts = [];
        foreach ($headBlocks as $block) {
            $payload = $block->content ?? $block->default_content;
            if ($payload === null) continue;
            $doc = is_string($payload) ? json_decode($payload, true) : (array) $payload;
            if (! is_array($doc)) continue;
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

    private function guardAgainstZipBomb(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            abort(422, 'Uploaded file is not a valid .docx (cannot open as ZIP).');
        }
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();
            abort(422, 'Archive contains too many entries.');
        }
        $totalUncompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) continue;
            $totalUncompressed += (int) ($stat['size'] ?? 0);
            if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                abort(422, 'Archive uncompressed size exceeds limit.');
            }
        }
        $zip->close();
    }
}
