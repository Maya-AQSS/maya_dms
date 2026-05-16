<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentVersionResource;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentVersionController extends Controller
{
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * GET /api/v1/documents/{document}/versions
     *
     * Metadatos de versiones (sin incluir el snapshot completo en el listado).
     */
    public function index(string $document): AnonymousResourceCollection
    {
        $doc = $this->documentService->findModelOrFail($document);
        $this->authorize('view', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        return DocumentVersionResource::collection(
            $this->documentService->listDocumentVersions($doc->id),
        );
    }

    /**
     * GET /api/v1/documents/{document}/versions/{version}
     *
     * Detalle de una versión con snapshot completo (solo lectura).
     */
    public function show(string $document, string $version): JsonResponse
    {
        $doc = $this->documentService->findModelOrFail($document);
        $this->authorize('view', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $detail = $this->documentService->findDocumentVersionDetailOrFail($document, $version);

        return (new DocumentVersionResource($detail))->response();
    }
}
