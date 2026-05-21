<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateVersionResource;
use App\Http\Resources\TemplateVersionSummaryResource;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
            $this->templateService
                ->listPublishedVersions($model->id)
                ->values(),
        );
    }

    /**
     * Detalle de un snapshot (incluye bloques).
     */
    public function show(string $template_version): TemplateVersionResource
    {
        $version = $this->templateService->findVersionOrFail($template_version);
        $templateId = (string) $version->versionable_id;

        $template = $this->templateService->findOrFailWithoutCatalogScope($templateId);
        if (! Gate::forUser(Auth::user())->allows('viewHistory', $template)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $template->process_id);

        return new TemplateVersionResource($version);
    }
}
