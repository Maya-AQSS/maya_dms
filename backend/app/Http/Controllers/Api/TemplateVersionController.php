<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\TemplateDownloaded;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateVersionResource;
use App\Http\Resources\TemplateVersionSummaryResource;
use App\Services\Contracts\TemplatePdfServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Maya\Auth\Models\JwtUser;

/**
 * Endpoints de lectura para versiones publicadas de Template. Split de
 * {@see TemplateController} para cumplir B9. Read-only — usa
 * `findOrFailWithoutCatalogScope` porque la visibilidad se delega al Gate.
 */
class TemplateVersionController extends Controller
{
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly TemplateServiceInterface $templateService,
        private readonly TemplatePdfServiceInterface $pdfService,
    ) {}

    /**
     * Historial de versiones publicadas (metadatos).
     */
    public function index(string $template): ResourceCollection
    {
        $model = $this->templateService->findOrFailWithoutCatalogScope($template);
        if (! Gate::forUser(Auth::user())->allows('viewHistory', $model)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        return TemplateVersionSummaryResource::collection(
            $this->templateService->listPublishedVersionSummaries($model->id),
        );
    }

    /**
     * Detalle de un snapshot (incluye bloques).
     */
    public function show(string $template_version): TemplateVersionResource
    {
        $version = $this->templateService->findVersionOrFail($template_version);
        $templateId = $version->versionableId;

        $template = $this->templateService->findOrFailWithoutCatalogScope($templateId);
        if (! Gate::forUser(Auth::user())->allows('viewHistory', $template)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $template->process_id);

        return new TemplateVersionResource(
            $this->templateService->findTemplateVersionDetailOrFail($template_version),
        );
    }

    /**
     * Descarga el PDF del snapshot de una versión histórica de la plantilla.
     * Gate: viewHistory (igual que index y show). Audita TemplateDownloaded.
     */
    public function downloadVersion(Request $request, string $template, string $version): Response
    {
        $model = $this->templateService->findOrFailWithoutCatalogScope($template);
        if (! Gate::forUser(Auth::user())->allows('viewHistory', $model)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        // generateForVersion valida internamente que la versión pertenece a la plantilla
        // y lanza NotFoundHttpException si no es así.
        $bytes = $this->pdfService->generateForVersion($template, $version);

        /** @var JwtUser $user */
        $user = Auth::user();
        TemplateDownloaded::dispatch(
            $template,
            (string) $user->id,
            'pdf',
            $version,
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="template-'.$template.'-v'.$version.'.pdf"',
        ]);
    }
}
