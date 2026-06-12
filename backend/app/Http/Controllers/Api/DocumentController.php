<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DocumentDto;
use App\Http\Concerns\AttachesDocumentCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\CloneDocumentRequest;
use App\Http\Requests\Documents\DestroyDocumentRequest;
use App\Http\Requests\Documents\ListDocumentsRequest;
use App\Http\Requests\Documents\ShowDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentWithBlocksResource;
use App\Models\Document;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\DocumentReviewService;
use Illuminate\Http\JsonResponse;
use Maya\Http\Concerns\RespondsWithEnvelope;

/**
 * CRUD canónico de Document (index/store/show/update/destroy/clone).
 * Las transiciones de estado viven en {@see DocumentStateController} y
 * las opciones/lookups en {@see DocumentOptionsController}. Split de B9.
 */
class DocumentController extends Controller
{
    use AttachesDocumentCanCloneMeta;
    use RespondsWithEnvelope;
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly DocumentServiceInterface $documentService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
        protected readonly DocumentReviewService $documentReviewService,
    ) {}

    /**
     * Listar documentos con paginación server-side (ADR-C).
     */
    public function index(ListDocumentsRequest $request): JsonResponse
    {
        $viewerId = (string) $request->user()->getAuthIdentifier();
        $page = $this->documentService->paginate(
            $request->toFilterDto(),
            $viewerId,
            function ($documents) use ($request, $viewerId): void {
                $this->attachCanCloneMeta($documents, $request);
                $this->apiTeamEmbedService->embedOnDocuments($documents, $viewerId);
            },
        );

        return $this->paginated($page, DocumentResource::class, $request);
    }

    /**
     * Crear documento anclado a la última versión publicada de la plantilla (o a una indicada).
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $document = $this->documentService->create(
            $request->toDto($userId, $userId),
            function (Document $model) use ($request, $userId): void {
                $this->attachCanCloneMeta($model, $request);
                $this->apiTeamEmbedService->embedOnDocument($model, $userId);
            },
        );
        $blocks = $this->documentService->blocksForDisplay($document->id);

        return response()->json([
            'data' => (new DocumentWithBlocksResource($document, $blocks))->toArray($request),
        ], 201);
    }

    /**
     * Clonar documento en borrador con sufijo "(copia)"; si hubo publicaciones, según último snapshot publicado.
     */
    public function clone(CloneDocumentRequest $request, string $document): JsonResponse
    {
        $source = $this->documentService->findModelOrFail($document);
        $this->assertOptionalProcessContextMatches((string) $source->process_id);

        $userId = (string) $request->user()->getAuthIdentifier();
        $copy = $this->documentService->clone(
            $document,
            $userId,
            function (Document $model) use ($request, $userId): void {
                $this->attachCanCloneMeta($model, $request);
                $this->apiTeamEmbedService->embedOnDocument($model, $userId);
            },
        );
        $blocks = $this->documentService->blocksForDisplay($copy->id);

        return response()->json([
            'data' => (new DocumentWithBlocksResource($copy, $blocks))->toArray($request),
        ], 201);
    }

    /**
     * Mostrar documento con bloques según la versión de plantilla anclada.
     */
    public function show(ShowDocumentRequest $request, string $document): JsonResponse
    {
        $viewerId = (string) $request->user()->getAuthIdentifier();
        $resolved = $request->resolveDocument();

        $this->assertOptionalProcessContextMatches((string) $resolved->process_id);

        $viewerContext = $this->documentService->resolveDocumentViewerContext($resolved, $document, $viewerId);
        $servePublishedSnapshot = $viewerContext['serve_published_snapshot'];
        $isAssignedReviewer = $viewerContext['is_assigned_reviewer'];

        $this->documentService->attachWorkingRevisionPresentationMeta($resolved);

        if ($servePublishedSnapshot) {
            $latestPublished = $this->documentService->findLatestPublishedVersion($resolved->id);
            if ($latestPublished === null) {
                abort(404);
            }
            $this->documentService->prepareDocumentForDisplay($resolved, $latestPublished, $isAssignedReviewer);
            $this->attachCanCloneMeta($resolved, $request);
            $this->documentService->attachLatestPublishedVersionMeta(collect([$resolved]));
            $this->documentService->attachShareMetadataForViewer(collect([$resolved]), $viewerId);
            $this->apiTeamEmbedService->embedOnDocument($resolved, $viewerId);
            $blocks = $this->documentService->blocksForDisplay((string) $resolved->id);

            return response()->json([
                'data' => (new DocumentWithBlocksResource(DocumentDto::fromModel($resolved), $blocks))->toArray($request),
            ]);
        }

        $this->documentService->prepareDocumentForDisplay($resolved, null, $isAssignedReviewer);
        $this->attachCanCloneMeta($resolved, $request);
        $this->documentService->attachLatestPublishedVersionMeta(collect([$resolved]));
        $this->documentService->attachShareMetadataForViewer(collect([$resolved]), $viewerId);
        $this->apiTeamEmbedService->embedOnDocument($resolved, $viewerId);
        $blocks = $this->documentService->blocksForDisplay((string) $resolved->id);

        return response()->json([
            'data' => (new DocumentWithBlocksResource(DocumentDto::fromModel($resolved), $blocks))->toArray($request),
        ]);
    }

    /**
     * Pool de validadores del documento (resuelto desde la versión de plantilla anclada).
     *
     * Autorizado con la policy `view`: cualquiera que pueda ver el documento ve sus
     * validadores, sin depender del acceso de lectura a la plantilla.
     */
    public function reviewers(ShowDocumentRequest $request, string $document): JsonResponse
    {
        $resolved = $request->resolveDocument();

        return response()->json(['data' => $this->documentService->getDocumentReviewerPool($resolved)]);
    }

    /**
     * Actualizar documento.
     */
    public function update(UpdateDocumentRequest $request, string $document): JsonResponse
    {
        $model = $request->resolveDocument();
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $userId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->update(
            $document,
            $request->toDto()->toArray(),
            function (Document $doc) use ($request, $userId): void {
                $this->attachCanCloneMeta($doc, $request);
                $this->apiTeamEmbedService->embedOnDocument($doc, $userId);
            },
        );

        return response()->json(['data' => (new DocumentResource($updated))->toArray($request)]);
    }

    /**
     * Eliminar documento.
     */
    public function destroy(DestroyDocumentRequest $request, string $document): JsonResponse
    {
        $model = $request->resolveDocument();
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $this->documentService->delete($document, $actorId);

        return response()->json([], 204);
    }
}
