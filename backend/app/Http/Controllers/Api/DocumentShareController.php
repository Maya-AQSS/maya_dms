<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TODO(permisos): reglas por documento (titular vs colaborador read/edit en
 * `document_shares`) y alinear con F-05.1; hoy se usa `authorize('update')` global.
 */
class DocumentShareController extends Controller
{
    public function __construct(
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * POST /api/v1/documents/{document}/shares
     * 
     * Comparte un documento con un usuario.
     * 
     * @param  string  $document
     * @return JsonResponse
     */
    public function store(Request $request, string $document): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('update', $doc);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * DELETE /api/v1/documents/{document}/shares/{userId}
     * 
     * Elimina un usuario de la lista de compartición de un documento.
     * 
     * @param  string  $document
     * @param  string  $userId
     * @return JsonResponse
     */
    public function destroy(Request $request, string $document, string $userId): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('update', $doc);

        return response()->json(['message' => 'Not implemented'], 501);
    }
}
