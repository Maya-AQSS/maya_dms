<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\UpdateDocumentBlockRequest;
use App\Http\Resources\DocumentBlockResource;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
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
        private readonly DocumentBlockRepositoryInterface $documentBlockRepository,
    ) {}

    /**
     * GET /api/v1/documents/{document}/blocks
     *
     * Lista los bloques de un documento.
     */
    public function index(string $document): AnonymousResourceCollection
    {
        $doc = $this->documentService->findModelOrFail($document);
        $this->authorize('listDocumentBlocks', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $blocks = $this->documentService->blocksForDisplay($document);

        return DocumentBlockResource::collection(
            array_map(fn ($dto) => $dto->toArray(), $blocks),
        );
    }

    /**
     * PUT /api/v1/documents/{document}/blocks/{block}
     *
     * Actualiza un bloque de un documento.
     */
    public function update(UpdateDocumentBlockRequest $request, string $document, string $block): JsonResponse
    {
        $doc = $this->documentService->findModelOrFail($document);
        $this->authorize('updateDocumentBlock', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $updated = $this->documentService->updateBlock(
            $request->toDto(
                documentId: $document,
                documentBlockId: $block,
                actorId: (string) $request->user()->getAuthIdentifier(),
            ),
        );

        return (new DocumentBlockResource($updated->toArray()))->response();
    }

    /**
     * DELETE /api/v1/documents/{document}/blocks/{block}
     *
     * Elimina un bloque opcional de un documento en borrador.
     */
    public function destroy(Request $request, string $document, string $block): Response
    {
        $doc = $this->documentService->findModelOrFail($document);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $this->authorize('deleteDocumentBlock', $doc);

        $blockModel = $this->documentBlockRepository->findInDocumentOrFail($block, $document);
        $blockModel->setRelation('document', $doc);

        $this->documentService->deleteOptionalBlock(new DeleteDocumentBlockDto(
            documentId: $document,
            documentBlockId: $block,
            actorId: (string) $request->user()->getAuthIdentifier(),
        ));

        return response()->noContent();
    }
}
