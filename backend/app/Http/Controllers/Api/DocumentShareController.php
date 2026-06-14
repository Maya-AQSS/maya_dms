<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentShareRequest;
use App\Http\Resources\DocumentShareResource;
use App\Policies\DocumentPolicy;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Compartición: alta/revocación gestionada solo por el titular ({@see DocumentPolicy::share}).
 */
class DocumentShareController extends Controller
{
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * POST /api/v1/documents/{document}/shares
     *
     * Comparte un documento con un usuario (permiso read o edit).
     */
    public function store(StoreDocumentShareRequest $request, string $document): JsonResponse
    {
        $doc = $this->documentService->findModelOrFail($document);
        $this->authorize('share', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $result = $this->documentService->upsertDocumentShare(
            $doc->id,
            $request->validated('user_id'),
            $request->validated('permission'),
            $actorId,
        );

        return response()->json(['data' => (new DocumentShareResource($result))->toArray($request)], 201);
    }

    /**
     * DELETE /api/v1/documents/{document}/shares/{userId}
     *
     * Revoca el acceso de un colaborador (idempotente).
     */
    public function destroy(Request $request, string $document, string $userId): Response
    {
        $doc = $this->documentService->findModelOrFail($document);
        $this->authorize('share', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $this->documentService->removeDocumentShare(
            $doc->id,
            $userId,
            (string) $request->user()->getAuthIdentifier(),
        );

        return response()->noContent();
    }
}
