<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Templates\TemplateDto;
use App\Http\Concerns\AttachesTemplateCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\CloneTemplateRequest;
use App\Http\Requests\Templates\IndexTemplateRequest;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD canónico de Template (index/store/show/update/destroy/clone). Las
 * transiciones de estado viven en {@see TemplateStateController}, las
 * versiones en {@see TemplateVersionController} y el sync de revisores
 * en {@see TemplateReviewersController}. Split de B9.
 *
 * Los métodos reciben el UUID como string (route {template}) para no usar
 * route model binding implícito antes del middleware JWT; el global scope
 * de {@see Template} depende de auth y fallaría en SubstituteBindings.
 */
class TemplateController extends Controller
{
    use AttachesTemplateCanCloneMeta;
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly TemplateServiceInterface $templateService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
    ) {}

    /**
     * Listar plantillas (filtros en query; sin paginación en servidor, como documentos).
     */
    public function index(IndexTemplateRequest $request): AnonymousResourceCollection
    {
        $templates = $this->templateService->listFiltered($request->toFilterDto());
        $this->attachCanCloneMeta($templates, $request);

        $this->apiTeamEmbedService->embedOnTemplates(
            $templates,
            (string) $request->user()->getAuthIdentifier(),
        );

        return TemplateResource::collection(
            $templates->map(static fn (Template $template) => TemplateDto::fromModel($template)),
        );
    }

    /**
     * Crear plantilla.
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->create($request->toCreateDto());
        $this->attachCanCloneMeta($template, $request);

        $this->apiTeamEmbedService->embedOnTemplate(
            $template,
            (string) $request->user()->getAuthIdentifier(),
        );

        return (new TemplateResource(TemplateDto::fromModel($template)))->response()->setStatusCode(201);
    }

    /**
     * Mostrar plantilla.
     */
    public function show(Request $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFailWithoutCatalogScope($template);
        if (! Gate::forUser($request->user())->allows('view', $model)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $model->process_id);
        $model->loadMissing(['reviewers', 'documentReviewers.user', 'creator', 'headVersion']);
        $this->attachCanCloneMeta($model, $request);
        $this->templateService->attachLatestPublishedVersionMeta(collect([$model]));

        $this->apiTeamEmbedService->embedOnTemplate(
            $model,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource(TemplateDto::fromModel($model));
    }

    /**
     * Actualizar plantilla.
     * La publicación exige actor distinto del creador vía la política de revisión de plantillas.
     */
    public function update(UpdateTemplateRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('update', [$model, $request->input('visibility_level')]);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $dto = $request->toUpdateDto();

        $updated = $this->templateService->update($model, $dto);
        $this->attachCanCloneMeta($updated, $request);

        $this->apiTeamEmbedService->embedOnTemplate(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource(TemplateDto::fromModel($updated));
    }

    /**
     * Eliminar plantilla (archivo si hay documentos asociados; si no, borrado físico).
     */
    public function destroy(string $template): JsonResponse|Response
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('delete', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $hardDeleted = $this->templateService->destroy($model->id, (string) Auth::id());

        if ($hardDeleted) {
            return response()->noContent();
        }

        return (new TemplateResource(TemplateDto::fromModel($this->templateService->findModelOrFail($model->id))))->response();
    }

    /**
     * Clonar plantilla en borrador personal con sufijo "(copia)" y mismos bloques.
     */
    public function clone(CloneTemplateRequest $_request, string $template): JsonResponse
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('clone', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $copy = $this->templateService->clone($template, (string) Auth::id());
        $this->attachCanCloneMeta($copy, $_request);

        return (new TemplateResource(TemplateDto::fromModel($copy)))->response()->setStatusCode(201);
    }
}
