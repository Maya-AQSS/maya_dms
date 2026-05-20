<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DocumentDto;
use App\Http\Concerns\AttachesDocumentCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\CloneDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\DocumentReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD canónico de Document (index/store/show/update/destroy/clone).
 * Las transiciones de estado viven en {@see DocumentStateController} y
 * las opciones/lookups en {@see DocumentOptionsController}. Split de B9.
 */
class DocumentController extends Controller
{
    use AttachesDocumentCanCloneMeta;
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly DocumentServiceInterface $documentService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
        protected readonly DocumentReviewService $documentReviewService,
    ) {}

    /**
     * Listar documentos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $viewerId = (string) $request->user()->getAuthIdentifier();
        $processId = $request->query('process_id');
        $processIdFilter = is_string($processId) && $processId !== '' ? $processId : null;

        $documents = $this->documentService->listOrderedByCreatedAtDesc($processIdFilter);
        $this->documentService->attachLatestPublishedVersionMeta($documents);
        $this->documentService->attachTemplateVersionNumbers($documents);
        $this->documentService->attachShareMetadataForViewer($documents, $viewerId);
        $this->apiTeamEmbedService->embedOnDocuments(
            $documents,
            $viewerId,
        );

        return DocumentResource::collection(
            $documents->map(static fn (Document $doc) => DocumentDto::fromModel($doc)),
        );
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
    public function show(Request $request, string $id): JsonResponse
    {
        $viewerId = (string) $request->user()->getAuthIdentifier();

        $servePublishedSnapshot = false;
        try {
            $document = $this->documentService->findModelOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // Entity not visible via user_access scope (e.g., draft owned by someone else).
            // Allow if a published snapshot exists — the controller will serve that instead.
            $document = $this->documentService->findModelOrFailWithoutUserAccess($id);
            if (! $this->documentService->hasPublishedSnapshot($document->id)) {
                abort(404);
            }
            $servePublishedSnapshot = true;
        }

        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $isCreator = (string) $document->created_by === $viewerId || (string) $document->owner_id === $viewerId;

        if (! $servePublishedSnapshot && ! $isCreator && in_array($document->status, ['draft', 'in_review'], true)) {
            // Any assigned reviewer (pending, approved, or rejected) can see real content while in_review.
            $isAssignedReviewer = $document->status === 'in_review'
                && $document->reviews()
                    ->where('reviewer_id', $viewerId)
                    ->exists();

            if (! $isAssignedReviewer) {
                $servePublishedSnapshot = true;
            }
        }

        if ($servePublishedSnapshot) {
            $latestPublished = $this->documentService->findLatestPublishedVersion($document->id);
            if ($latestPublished === null) {
                abort(404);
            }
            $document->setRelation('headVersion', $latestPublished);
            $this->attachCanCloneMeta($document, $request);
            $this->documentService->attachShareMetadataForViewer(collect([$document]), $viewerId);
            $document->loadMissing(['owner']);
            $this->apiTeamEmbedService->embedOnDocument($document, $viewerId);
            $blocks = $this->documentService->blocksForDisplay($document);

            return response()->json([
                'data' => array_merge(
                    (new DocumentResource(DocumentDto::fromModel($document)))->toArray($request),
                    ['blocks' => $blocks],
                ),
            ]);
        }

        $document->setAttribute(
            'has_review_comments',
            $document->comments()->exists(),
        );
        $this->attachCanCloneMeta($document, $request);
        $this->documentService->attachShareMetadataForViewer(collect([$document]), $viewerId);
        $document->loadMissing(['owner']);
        $this->apiTeamEmbedService->embedOnDocument(
            $document,
            $viewerId,
        );
        $blocks = $this->documentService->blocksForDisplay($document);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($document)))->toArray($request),
                ['blocks' => $blocks],
            ),
        ]);
    }

    /**
     * Actualizar documento.
     */
    public function update(UpdateDocumentRequest $request, string $id): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('update', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $updated = $this->documentService->update($id, $request->toDto()->toArray());
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
    public function destroy(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('delete', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $this->documentService->delete($id, $actorId);

        return response()->json([], 204);
    }
}
