<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentServiceInterface $commentService,
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * Listar comentarios de un documento.
     */
    public function index(string $document): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('view', $doc);

        return response()->json(['data' => []]);
    }

    /**
     * Crear comentario en un documento.
     */
    public function store(Request $request, string $document): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('view', $doc);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Mostrar comentario.
     */
    public function show(string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);

        return response()->json(['data' => $commentModel]);
    }

    /**
     * Actualizar comentario.
     */
    public function update(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Eliminar comentario.
     */
    public function destroy(string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Marcar comentario como resuelto.
     */
    public function resolve(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);

        return response()->json(['message' => 'Not implemented'], 501);
    }
}
