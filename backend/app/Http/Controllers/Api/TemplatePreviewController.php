<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\TemplatePdfServiceInterface;
use App\Services\Contracts\TemplateRenderServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Devuelve el HTML themed de una plantilla. Construye un "preview document"
 * sintético a partir de los `default_content` de los template_blocks y lo
 * renderiza con el mismo Blade que usan los documentos reales — el theme
 * asignado a la plantilla se aplica idénticamente.
 */
class TemplatePreviewController extends Controller
{
    public function __construct(
        private readonly TemplateRenderServiceInterface $renderer,
        private readonly TemplateServiceInterface $templateService,
        private readonly TemplatePdfServiceInterface $pdf,
    ) {}

    public function show(string $template): Response
    {
        // La policy ya gobierna quién puede ver la plantilla; usamos service/repository
        // sin global scopes para que la policy decida (no el catálogo).
        $model = $this->templateService->findOrFailWithoutCatalogScope($template);
        if (! Gate::forUser(request()->user())->allows('view', $model)) {
            abort(404);
        }

        $html = $this->renderer->renderHtml($template, previewMode: true);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // CSP: igual que DocumentPreviewController — `script-src 'self'`
            // para permitir paged.js servido desde /vendor/pagedjs/.
            'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET /api/v1/templates/{template}/pdf
     * Genera y descarga el PDF/UA de la plantilla de forma SÍNCRONA (WeasyPrint a
     * memoria, mismo patrón que el PDF de muestra de themes).
     */
    public function pdf(string $template): Response
    {
        $model = $this->templateService->findOrFailWithoutCatalogScope($template);
        if (! Gate::forUser(request()->user())->allows('view', $model)) {
            abort(404);
        }

        $bytes = $this->pdf->generateSample($template);
        $filename = (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) ($model->name ?? 'plantilla')).'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
