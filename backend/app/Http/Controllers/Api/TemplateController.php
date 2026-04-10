<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\CloneTemplateRequest;
use App\Http\Requests\Templates\IndexTemplateRequest;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Los métodos reciben el UUID como string (route {template}) para no usar
 * route model binding implícito antes del middleware JWT; el global scope
 * de {@see \App\Models\Template} depende de auth y fallaría en SubstituteBindings.
 */
class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateServiceInterface $templateService,
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

        return TemplateResource::collection($paginator);
    }

    /**
     * Crear plantilla.
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->create($request->toCreateDto());

        return (new TemplateResource($template))->response()->setStatusCode(201);
    }

    /**
     * Mostrar plantilla.
     */
    public function show(string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('view', $model);

        return new TemplateResource($model);
    }

    /**
     * Actualizar plantilla.
     * La publicación exige actor distinto del creador vía {@see \App\Policies\TemplatePolicy::review}.
     */
    public function update(UpdateTemplateRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);

        $dto = $request->toUpdateDto();

        $targetStatus = $dto->setStatus ? $dto->status : $model->status;
        if ($targetStatus === 'published' && $model->status !== 'published') {
            $this->authorize('review', $model);
        }

        $updated = $this->templateService->update($model->id, $dto);

        return new TemplateResource($updated);
    }

    /**
     * Eliminar plantilla (archivo si hay documentos asociados; si no, borrado físico).
     */
    public function destroy(string $template): JsonResponse|\Illuminate\Http\Response
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
    public function clone(CloneTemplateRequest $request, string $template): JsonResponse
    {
        $copy = $this->templateService->clone($template, (string) Auth::id());

        return (new TemplateResource($copy))->response()->setStatusCode(201);
    }
}
