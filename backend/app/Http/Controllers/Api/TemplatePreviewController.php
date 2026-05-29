<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\Contracts\TemplateRenderServiceInterface;
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
    ) {}

    public function show(string $template): Response
    {
        // La policy ya gobierna quién puede ver la plantilla; usamos la query
        // sin global scopes para que la policy decida (no el catálogo).
        $model = Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->findOrFail($template);
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
}
