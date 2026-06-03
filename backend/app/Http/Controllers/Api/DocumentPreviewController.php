<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentRenderServiceInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Devuelve el HTML themed del documento. Sirve tanto como vista previa en
 * navegador como base para WeasyPrint en Phase 4 (mismo HTML).
 */
class DocumentPreviewController extends Controller
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentRenderServiceInterface $renderer,
    ) {}

    public function show(string $document): Response
    {
        $model = $this->documentRepository->findOrFailForRefreshAfterMutation($document);

        if (! Gate::forUser(request()->user())->allows('view', $model)) {
            abort(404);
        }

        // previewMode = true → el Blade carga paged.js para paginación A4 en
        // el navegador. El export PDF (DocumentPdfService) sigue invocando
        // `renderHtml($id)` sin flag → false por defecto.
        $html = $this->renderer->renderHtml($document, previewMode: true);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // CSP: permitimos `script-src 'self'` (paged.js servido desde
            // /vendor/pagedjs/ — mismo origen). Sin CDN externo, sin inline
            // scripts más allá del wrapper IIFE que pagedjs ya emite.
            'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
