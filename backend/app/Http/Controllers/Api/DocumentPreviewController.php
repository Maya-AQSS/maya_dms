<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Contracts\DocumentRenderServiceInterface;
use Illuminate\Http\Response;

/**
 * Devuelve el HTML themed del documento. Sirve tanto como vista previa en
 * navegador como base para WeasyPrint en Phase 4 (mismo HTML).
 */
class DocumentPreviewController extends Controller
{
    public function __construct(
        private readonly DocumentRenderServiceInterface $renderer,
    ) {}

    public function show(string $document): Response
    {
        $this->authorize('view', Document::query()->findOrFail($document));

        $html = $this->renderer->renderHtml($document);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // CSP estricto: el preview no debe ejecutar JS — solo HTML/CSS sanitizado.
            'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; script-src 'none'; object-src 'none'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
