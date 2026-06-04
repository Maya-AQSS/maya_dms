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
use App\Models\Document;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\DocumentReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $document = $this->documentService->create($request->toDto($userId, $userId));
        $this->attachCanCloneMeta($document, $request);
        $this->apiTeamEmbedService->embedOnDocument($document, $userId);
        $blocks = $this->documentService->blocksForDisplay($document);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($document)))->toArray($request),
                ['blocks' => $blocks],
            ),
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
        $copy = $this->documentService->clone($document, $userId);
        $this->attachCanCloneMeta($copy, $request);
        $this->apiTeamEmbedService->embedOnDocument($copy, $userId);
        $blocks = $this->documentService->blocksForDisplay($copy);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($copy)))->toArray($request),
                ['blocks' => $blocks],
            ),
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
            $blocks = $this->documentService->blocksForDisplay($resolved);

            return response()->json([
                'data' => array_merge(
                    (new DocumentResource(DocumentDto::fromModel($resolved)))->toArray($request),
                    ['blocks' => $blocks],
                ),
            ]);
        }

        $this->documentService->prepareDocumentForDisplay($resolved, null, $isAssignedReviewer);
        $this->attachCanCloneMeta($resolved, $request);
        $this->documentService->attachLatestPublishedVersionMeta(collect([$resolved]));
        $this->documentService->attachShareMetadataForViewer(collect([$resolved]), $viewerId);
        $this->apiTeamEmbedService->embedOnDocument($resolved, $viewerId);
        $blocks = $this->documentService->blocksForDisplay($resolved);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($resolved)))->toArray($request),
                ['blocks' => $blocks],
            ),
        ]);
    }

    /**
     * Actualizar documento.
     */
    public function update(UpdateDocumentRequest $request, string $document): JsonResponse
    {
        $model = $request->resolveDocument();
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->documentService->update($document, $request->toDto()->toArray());
        $this->attachCanCloneMeta($updated, $request);
        $this->apiTeamEmbedService->embedOnDocument(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return response()->json(['data' => (new DocumentResource(DocumentDto::fromModel($updated)))->toArray($request)]);
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
