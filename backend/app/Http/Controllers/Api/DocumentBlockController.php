<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\UpdateDocumentBlockRequest;
use App\Http\Resources\DocumentBlockResource;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DocumentBlockController extends Controller
{
    use ValidatesOptionalProcessContext;

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
    public function index(string $document): AnonymousResourceCollection
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('view', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        return DocumentBlockResource::collection(
            $this->documentService->blocksForDisplay($doc),
        );
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
    public function update(UpdateDocumentBlockRequest $request, string $document, string $block): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('update', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $updated = $this->documentService->updateBlock(
            $request->toDto(
                documentId: $document,
                documentBlockId: $block,
                actorId: (string) $request->user()->getAuthIdentifier(),
            ),
        );

        return (new DocumentBlockResource($updated))->response();
    }

    /**
     * DELETE /api/v1/documents/{document}/blocks/{block}
     *
     * Elimina un bloque opcional de un documento en borrador.
     */
    public function destroy(Request $request, string $document, string $block): Response
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('update', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $this->documentService->deleteOptionalBlock(new DeleteDocumentBlockDto(
            documentId: $document,
            documentBlockId: $block,
            actorId: (string) $request->user()->getAuthIdentifier(),
        ));

        return response()->noContent();
    }
}
