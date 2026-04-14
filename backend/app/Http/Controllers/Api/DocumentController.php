<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * Listar documentos.
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: listar documentos (filtros, paginación)

        return response()->json(['data' => []]);
    }

    /**
     * Crear documento anclado a la última versión publicada de la plantilla (o a una indicada).
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $userId = (string) Auth::id();
        $document = $this->documentService->create($request->toDto($userId, $userId));
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
