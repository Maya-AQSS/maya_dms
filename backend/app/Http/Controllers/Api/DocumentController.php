<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
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
        $documents = $this->documentService->listOrderedByCreatedAtDesc();
        $this->apiTeamEmbedService->embedOnDocuments(
            $documents,
            (string) $request->user()->getAuthIdentifier(),
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
    public function creationOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'string'],
        ]);

        $options = $this->documentService->creationOptionsForModule($validated['module_id']);
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
    public function createFromModule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'string'],
            'template_version_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $userId = (string) $request->user()->getAuthIdentifier();
        $document = $this->documentService->createFromModule(
            $validated['module_id'],
            $userId,
            $validated['template_version_id'] ?? null,
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
     * Mostrar documento con bloques según la versión de plantilla anclada (F-03.4).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('view', $document);
        $this->apiTeamEmbedService->embedOnDocument(
            $document,
            (string) $request->user()->getAuthIdentifier(),
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
    public function update(Request $request, string $id): JsonResponse
    {
        // TODO: DocumentService::update(...)
        $this->documentService->findOrFail($id);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Eliminar documento.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->documentService->findOrFail($id);
        // TODO: borrado lógico / política propia

        return response()->json(['message' => 'Not implemented'], 501);
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
    public function publish(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findOrFail($id);
        $this->authorize('review', $document);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->publishDocument($document->id, $actorId);

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
        $this->documentService->findOrFail($id);

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
