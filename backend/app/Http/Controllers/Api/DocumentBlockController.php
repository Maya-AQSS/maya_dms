<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
class DocumentBlockController extends Controller
{
    public function __construct(
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * GET /api/v1/documents/{document}/blocks
     * 
     * Lista los bloques de un documento.
     * 
     * @param  string  $document
     * @return JsonResponse
     */
    public function index(string $document): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('view', $doc);

        $blocks = $this->documentService->blocksForDisplay($doc);

        return response()->json(['data' => $blocks]);
    }

    /**
     * PUT /api/v1/documents/{document}/blocks/{block}
     * 
     * Actualiza un bloque de un documento.
     * 
     * @param  string  $document
     * @param  string  $block
     * @return JsonResponse
     */
    public function update(string $document, string $_block): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('view', $doc);

        return response()->json(['message' => 'Not implemented'], 501);
    }
}
