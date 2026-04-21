<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentCreateFromModuleRequest;
use App\Http\Requests\Documents\DocumentCreationOptionsRequest;
use App\Http\Requests\Documents\PublishDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentServiceInterface $documentService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
    ) {}

    /**
     * Listar documentos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $viewerId = (string) $request->user()->getAuthIdentifier();
        $documents = $this->documentService->listOrderedByCreatedAtDesc();
        $this->documentService->attachShareMetadataForViewer($documents, $viewerId);
        $this->apiTeamEmbedService->embedOnDocuments(
            $documents,
            $viewerId,
        );

        return DocumentResource::collection($documents);
    }

    /**
     * Crear documento anclado a la última versión publicada de la plantilla (o a una indicada).
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $document = $this->documentService->create($request->toDto($userId, $userId));
        $this->apiTeamEmbedService->embedOnDocument($document, $userId);
        $blocks = $this->documentService->blocksForDisplay($document);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource($document))->toArray($request),
                ['blocks' => $blocks],
            ),
        ], 201);
    }

    /**
     * Opciones para crear una programación desde la vista de módulo.
     */
    public function creationOptions(DocumentCreationOptionsRequest $request): JsonResponse
    {
        $options = $this->documentService->creationOptionsForModule($request->validated('module_id'));
        $count = count($options);

        if ($count === 0) {
            return response()->json([
                'data' => [
                    'can_create' => false,
                    'mode' => 'none',
                    'message' => 'No hay plantillas publicadas disponibles para este módulo.',
                    'options' => [],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'can_create' => true,
                'mode' => $count === 1 ? 'auto' : 'select',
                'message' => null,
                'options' => $options,
            ],
        ]);
    }

    /**
     * Crear una programación desde módulo con selección opcional de versión de plantilla.
     */
    public function createFromModule(DocumentCreateFromModuleRequest $request): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $document = $this->documentService->createFromModule(
            $request->validated('module_id'),
            $userId,
            $request->validated('template_version_id') ?? null,
        );
        $this->apiTeamEmbedService->embedOnDocument($document, $userId);
        $blocks = $this->documentService->blocksForDisplay($document);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource($document))->toArray($request),
                ['blocks' => $blocks],
            ),
        ], 201);
    }

    /**
     * GET /api/v1/documents/{document}/template-version-status
     *
     * Indica si existe una versión de plantilla publicada más reciente que la anclada al documento.
     */
    public function templateVersionStatus(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('view', $document);

        return response()->json([
            'data' => $this->documentService->templateVersionStatus($document->id),
        ]);
    }

    /**
     * Mostrar documento con bloques según la versión de plantilla anclada.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $viewerId = (string) $request->user()->getAuthIdentifier();
        $document = $this->documentService->findOrFail($id);
        $this->authorize('view', $document);
        $this->documentService->attachShareMetadataForViewer(collect([$document]), $viewerId);
        $this->apiTeamEmbedService->embedOnDocument(
            $document,
            $viewerId,
        );
        $blocks = $this->documentService->blocksForDisplay($document);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource($document))->toArray($request),
                ['blocks' => $blocks],
            ),
        ]);
    }

    /**
     * Actualizar documento.
     */
    public function update(UpdateDocumentRequest $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('update', $document);

        $updated = $this->documentService->update($id, $request->validated());
        $this->apiTeamEmbedService->embedOnDocument(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return response()->json(['data' => (new DocumentResource($updated))->toArray($request)]);
    }

    /**
     * Eliminar documento.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('delete', $document);

        $this->documentService->delete($id);

        return response()->json([], 204);
    }

    /**
     * Enviar documento a revisión.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('submit', $document);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->submitToReview($document->id, $actorId);

        return response()->json(['data' => $updated]);
    }

    /**
     * Publicar documento.
     */
    public function publish(PublishDocumentRequest $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('review', $document);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->publishDocument(
            $document->id,
            $actorId,
            $request->validated('changelog'),
        );

        return response()->json(['data' => $updated]);
    }

    /**
     * Rechazar documento y vuelta a borrador.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('review', $document);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->rejectDocument($document->id, $actorId);

        return response()->json(['data' => $updated]);
    }

    /**
     * Delegar documento a otro usuario.
     */
    public function delegate(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('view', $document);

        $validated = $request->validate([
            'new_owner_id' => ['required', 'string'],
        ]);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->delegateOwner(
            $id,
            $validated['new_owner_id'],
            $actorId,
        );

        return response()->json(['data' => $updated]);
    }
}
