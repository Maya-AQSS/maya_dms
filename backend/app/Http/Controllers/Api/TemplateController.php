<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\CloneTemplateRequest;
use App\Http\Requests\Templates\IndexTemplateRequest;
use App\Http\Requests\Templates\PublishTemplateRequest;
use App\Http\Requests\Templates\SyncTemplateUsersRequest;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Http\Resources\TemplateVersionResource;
use App\Http\Resources\TemplateVersionSummaryResource;
use App\Policies\TemplatePolicy;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Los métodos reciben el UUID como string (route {template}) para no usar
 * route model binding implícito antes del middleware JWT; el global scope
 * de {@see Template} depende de auth y fallaría en SubstituteBindings.
 */
class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateServiceInterface $templateService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
    ) {}

    /**
     * Listar plantillas (filtros en query; paginación máx. 20).
     */
    public function index(IndexTemplateRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->templateService->paginateFiltered(
            $request->toFilterDto(),
            $request->perPage(),
        );

        $this->apiTeamEmbedService->embedOnTemplatePaginator(
            $paginator,
            (string) $request->user()->getAuthIdentifier(),
        );

        return TemplateResource::collection($paginator);
    }

    /**
     * Crear plantilla.
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->create($request->toCreateDto());

        $this->apiTeamEmbedService->embedOnTemplate(
            $template,
            (string) $request->user()->getAuthIdentifier(),
        );

        return (new TemplateResource($template))->response()->setStatusCode(201);
    }

    /**
     * Mostrar plantilla.
     */
    public function show(Request $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('view', $model);
        $model->loadMissing(['reviewers', 'documentReviewers']);

        $this->apiTeamEmbedService->embedOnTemplate(
            $model,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource($model);
    }

    /**
     * Actualizar plantilla.
     * La publicación exige actor distinto del creador vía {@see TemplatePolicy::review}.
     */
    public function update(UpdateTemplateRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('update', [$model, $request->input('visibility_level')]);

        $dto = $request->toUpdateDto();

        $updated = $this->templateService->update($model->id, $dto);

        $this->apiTeamEmbedService->embedOnTemplate(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource($updated);
    }

    /**
     * Eliminar plantilla (archivo si hay documentos asociados; si no, borrado físico).
     */
    public function destroy(string $template): JsonResponse|Response
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('delete', $model);

        $hardDeleted = $this->templateService->destroy($model->id, (string) Auth::id());

        if ($hardDeleted) {
            return response()->noContent();
        }

        return (new TemplateResource($this->templateService->findOrFail($model->id)))->response();
    }

    /**
     * Clonar plantilla en borrador personal con sufijo "(copia)" y mismos bloques.
     */
    public function clone(CloneTemplateRequest $_request, string $template): JsonResponse
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('clone', $model);

        $copy = $this->templateService->clone($template, (string) Auth::id());

        return (new TemplateResource($copy))->response()->setStatusCode(201);
    }

    /**
     * Borrador → en revisión (autor o quien puede editar).
     */
    public function submitForReview(string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('submitForReview', $model);

        $updated = $this->templateService->submitForReview($model->id, (string) Auth::id());

        return new TemplateResource($updated);
    }

    /**
     * En revisión → borrador (revisor).
     */
    public function rejectReview(string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('review', $model);

        $updated = $this->templateService->rejectReview($model->id, (string) Auth::id());

        return new TemplateResource($updated);
    }

    /**
     * En revisión → aprobación del revisor activo.
     *
     * Si todos los revisores han aprobado, la plantilla se publica automáticamente.
     * En modo secuencial verifica que los stages anteriores estén aprobados.
     */
    public function approveReview(string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('review', $model);

        $updated = $this->templateService->approveReview($model->id, (string) Auth::id());

        return new TemplateResource($updated);
    }

    /**
     * En revisión → publicado + snapshot (revisor; changelog obligatorio).
     */
    public function publish(PublishTemplateRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('publish', $model);

        $updated = $this->templateService->publishWithSnapshot(
            $model->id,
            $request->validated('changelog'),
            (string) Auth::id(),
        );

        return new TemplateResource($updated);
    }

    /**
     * Historial de versiones publicadas (metadatos).
     */
    public function versions(string $template): ResourceCollection
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('view', $model);

        return TemplateVersionSummaryResource::collection(
            $this->templateService->listPublishedVersions($model->id),
        );
    }

    /**
     * Detalle de un snapshot (incluye bloques).
     */
    public function showVersion(string $template_version): TemplateVersionResource
    {
        $version = $this->templateService->findVersionOrFail($template_version);
        $template = $this->templateService->findOrFail($version->template_id);
        $this->authorize('view', $template);

        return new TemplateVersionResource($version);
    }

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(SyncTemplateUsersRequest $request, string $template): JsonResponse
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('update', $model);

        $this->templateService->syncReviewers($model->id, $request->validated('user_ids'));

        return response()->json(['message' => 'Revisores de plantilla sincronizados correctamente.']);
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(SyncTemplateUsersRequest $request, string $template): JsonResponse
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('update', $model);

        $this->templateService->syncDocumentReviewers($model->id, $request->validated('user_ids'));

        return response()->json(['message' => 'Validadores de documento sincronizados correctamente.']);
    }
}
