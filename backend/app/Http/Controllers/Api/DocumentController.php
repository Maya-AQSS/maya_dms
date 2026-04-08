<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Crear documento.
     */
    public function store(Request $request): JsonResponse
    {
        // TODO: DocumentService::create(...)

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Mostrar documento.
     */
    public function show(Document $document): JsonResponse
    {
        // TODO: DocumentResource

        return response()->json(['data' => $document]);
    }

    /**
     * Actualizar documento.
     */
    public function update(Request $request, Document $document): JsonResponse
    {
        // TODO: DocumentService::update(...)

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Eliminar documento.
     */
    public function destroy(Document $document): JsonResponse
    {
        // TODO: borrado lógico / política propia

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Enviar documento a revisión.
     */
    public function submit(Request $request, Document $document): JsonResponse
    {
        $this->authorize('submit', $document);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->submitToReview($document->id, $actorId);

        return response()->json(['data' => $updated]);
    }

    /**
     * Publicar documento.
     */
    public function publish(Request $request, Document $document): JsonResponse
    {
        $this->authorize('review', $document);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->publishDocument($document->id, $actorId);

        return response()->json(['data' => $updated]);
    }

    /**
     * Rechazar documento y vuelta a borrador.
     */
    public function reject(Request $request, Document $document): JsonResponse
    {
        $this->authorize('review', $document);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->rejectDocument($document->id, $actorId);

        return response()->json(['data' => $updated]);
    }

    /**
     * Delegar documento a otro usuario.
     */
    public function delegate(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'new_owner_id' => ['required', 'string'],
        ]);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->delegateOwner(
            $document->id,
            $validated['new_owner_id'],
            $actorId,
        );

        return response()->json(['data' => $updated]);
    }
}
