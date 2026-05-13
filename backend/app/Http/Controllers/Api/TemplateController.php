<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\CloneTemplateRequest;
use App\Http\Requests\Templates\IndexTemplateRequest;
use App\Http\Requests\Templates\PublishTemplateRequest;
use App\Http\Requests\Templates\StartNewTemplateRevisionRequest;
use App\Http\Requests\Templates\SyncTemplateUsersRequest;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Http\Resources\TemplateVersionResource;
use App\Http\Resources\TemplateVersionSummaryResource;
use App\Models\Template;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Los métodos reciben el UUID como string (route {template}) para no usar
 * route model binding implícito antes del middleware JWT; el global scope
 * de {@see Template} depende de auth y fallaría en SubstituteBindings.
 */
class TemplateController extends Controller
{
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
        $this->attachLatestPublishedVersionMeta($templates);
        $this->attachCanCloneMeta($templates, $request);

        $this->apiTeamEmbedService->embedOnTemplates(
            $templates,
            (string) $request->user()->getAuthIdentifier(),
        );

        return TemplateResource::collection($templates);
    }

    /**
     * Adjunta `can_clone` desde policy para evitar Gate por recurso en listados.
     *
     * @param  Template|Collection<int, Template>  $templates
     */
    private function attachCanCloneMeta(Template|Collection $templates, Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        $attach = function (Template $template) use ($user): void {
            $template->setAttribute('can_clone', Gate::forUser($user)->allows('clone', $template));
        };

        if ($templates instanceof Template) {
            $attach($templates);
            return;
        }

        foreach ($templates as $template) {
            $attach($template);
        }
    }

    /**
     * Adjunta metadatos de última versión publicada por plantilla para construir vistas fallback.
     *
     * @param  \Illuminate\Support\Collection<int, Template>  $templates
     */
    private function attachLatestPublishedVersionMeta(\Illuminate\Support\Collection $templates): void
    {
        if ($templates->isEmpty()) {
            return;
        }

        $ids = $templates->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->values()->all();
        if ($ids === []) {
            return;
        }

        $rows = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->whereIn('versionable_id', $ids)
            ->where('status', 'published')
            ->where('version_number', '>', 0)
            ->orderByDesc('version_number')
            ->get(['versionable_id', 'id', 'version_number', 'snapshot_data']);

        /** @var array<string, object{versionable_id:string,id:string,version_number:int}> $latestByTemplate */
        $latestByTemplate = [];
        foreach ($rows as $row) {
            $templateId = (string) $row->versionable_id;
            if (! isset($latestByTemplate[$templateId])) {
                $latestByTemplate[$templateId] = (object) [
                    'versionable_id' => $templateId,
                    'id' => (string) $row->id,
                    'version_number' => (int) $row->version_number,
                    'name' => $this->extractPublishedTemplateNameFromSnapshotRow($row->snapshot_data),
                ];
            }
        }

        foreach ($templates as $template) {
            $meta = $latestByTemplate[(string) $template->id] ?? null;
            $template->setAttribute('latest_published_version_id', $meta?->id);
            $template->setAttribute('latest_published_version_number', $meta?->version_number);
            $template->setAttribute('latest_published_name', $meta?->name);
        }
    }

    private function extractPublishedTemplateNameFromSnapshotRow(mixed $snapshot): ?string
    {
        if (is_string($snapshot) && $snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        if (! is_array($snapshot)) {
            return null;
        }

        $name = data_get($snapshot, 'template.name');
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return $name;
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

        return (new TemplateResource($template))->response()->setStatusCode(201);
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

        $this->apiTeamEmbedService->embedOnTemplate(
            $model,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource($model);
    }

    /**
     * Actualizar plantilla.
     * La publicación exige actor distinto del creador vía la política de revisión de plantillas.
     */
    public function update(UpdateTemplateRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('update', [$model, $request->input('visibility_level')]);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $dto = $request->toUpdateDto();

        $updated = $this->templateService->update($model, $dto);
        $this->attachCanCloneMeta($updated, $request);

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
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

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
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $copy = $this->templateService->clone($template, (string) Auth::id());
        $this->attachCanCloneMeta($copy, $_request);

        return (new TemplateResource($copy))->response()->setStatusCode(201);
    }

    /**
     * Borrador → en revisión (autor o quien puede editar).
     */
    public function submitForReview(string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('submitForReview', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->submitForReview($model->id, (string) Auth::id());
        $updated->setAttribute('can_clone', Gate::forUser(Auth::user())->allows('clone', $updated));

        return new TemplateResource($updated);
    }

    /**
     * En revisión → borrador (revisor).
     */
    public function rejectReview(string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('review', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->rejectReview($model->id, (string) Auth::id());
        $updated->setAttribute('can_clone', Gate::forUser(Auth::user())->allows('clone', $updated));

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
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->approveReview($model->id, (string) Auth::id());
        $updated->setAttribute('can_clone', Gate::forUser(Auth::user())->allows('clone', $updated));

        return new TemplateResource($updated);
    }

    /**
     * Publicación explícita de plantilla + snapshot (changelog obligatorio para v2+).
     *
     * Flujos aceptados:
     *  - Creador sin revisores asignados: puede publicar directamente desde `draft`.
     *  - Revisor asignado: puede publicar desde `in_review` tras el proceso de revisión.
     */
    public function publish(PublishTemplateRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('publish', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->publishWithSnapshot(
            $model->id,
            $request->validated('changelog'),
            (string) Auth::id(),
        );
        $this->attachCanCloneMeta($updated, $request);

        return new TemplateResource($updated);
    }

    /**
     * Publicada → borrador (nueva versión de edición sobre la misma plantilla).
     */
    public function startNewVersion(StartNewTemplateRevisionRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->startNewRevisionCycle(
            $model->id,
            (string) $request->user()->getAuthIdentifier(),
        );
        $this->attachCanCloneMeta($updated, $request);

        $this->apiTeamEmbedService->embedOnTemplate(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource($updated);
    }

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación.
     */
    public function destroyVersion(Request $request, string $template, string $version): TemplateResource
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('update', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->destroyVersion(
            $model->id,
            $version,
            (string) $request->user()->getAuthIdentifier(),
        );
        $this->attachCanCloneMeta($updated, $request);

        $this->apiTeamEmbedService->embedOnTemplate(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource($updated);
    }

    /**
     * Historial de versiones publicadas (metadatos).
     */
    public function versions(string $template): ResourceCollection
    {
        $model = $this->templateService->findOrFailWithoutCatalogScope($template);
        if (! Gate::forUser(Auth::user())->allows('view', $model)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $excludeCurrentPublishedVersion = (string) $model->status === 'published';
        $currentVersion = (int) $model->version;

        return TemplateVersionSummaryResource::collection(
            $this->templateService
                ->listPublishedVersions($model->id)
                ->reject(
                    static fn ($row): bool =>
                        $excludeCurrentPublishedVersion && (int) $row->version_number === $currentVersion,
                )
                ->values(),
        );
    }

    /**
     * Detalle de un snapshot (incluye bloques).
     */
    public function showVersion(string $template_version): TemplateVersionResource
    {
        $version = $this->templateService->findVersionOrFail($template_version);
        $templateId = (string) $version->versionable_id;

        $template = $this->templateService->findOrFailWithoutCatalogScope($templateId);
        if (! Gate::forUser(Auth::user())->allows('view', $template)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $template->process_id);

        return new TemplateVersionResource($version);
    }

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(SyncTemplateUsersRequest $request, string $template): JsonResponse
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('update', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $this->templateService->syncReviewers($model->id, $request->toDto());

        return response()->json(['message' => 'Revisores de plantilla sincronizados correctamente.']);
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(SyncTemplateUsersRequest $request, string $template): JsonResponse
    {
        $model = $this->templateService->findOrFail($template);
        $this->authorize('update', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $this->templateService->syncDocumentReviewers($model->id, $request->toDto());

        return response()->json(['message' => 'Validadores de documento sincronizados correctamente.']);
    }
}
