<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Services\Contracts\ThemePdfServiceInterface;
use App\Services\Contracts\ThemeRenderServiceInterface;
use App\Services\Contracts\ThemeServiceInterface;
use App\Support\PreviewHeaders;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Paso de Verificación del wizard de themes: previsualización HTML (paged.js)
 * y PDF de muestra. Ambos construyen un documento sintético con lorem ipsum
 * y aplican el theme con el mismo Blade que documentos/plantillas reales.
 */
class ThemePreviewController extends Controller
{
    public function __construct(
        private readonly ThemeRenderServiceInterface $renderer,
        private readonly ThemePdfServiceInterface $pdf,
        private readonly ThemeServiceInterface $service,
    ) {}

    /**
     * GET /api/v1/themes/{theme}/preview — HTML themed con paged.js.
     */
    public function show(string $theme): Response
    {
        $this->authorizeView($theme);

        $html = $this->renderer->renderHtml($theme, previewMode: true);

        return response($html, 200, PreviewHeaders::forHtml());
    }

    /**
     * GET /api/v1/themes/{theme}/sample-pdf — PDF/UA de muestra (inline).
     */
    public function samplePdf(string $theme): Response
    {
        $this->authorizeView($theme);

        $bytes = $this->pdf->generateSample($theme);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="theme-preview.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeView(string $themeId): void
    {
        $dto = $this->service->get($themeId);

        $model = new Theme;
        $model->id = $dto->id;
        $model->created_by = $dto->createdBy;
        $model->status = $dto->status;
        $model->is_system = $dto->isSystem;

        if (! Gate::forUser(request()->user())->allows('view', $model)) {
            abort(404);
        }
    }
}
