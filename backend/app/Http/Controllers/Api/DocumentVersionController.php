<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;

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
    public function index(string $document): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('view', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $rows = $this->documentService->listDocumentVersions($doc->id);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/v1/documents/{document}/versions/{version}
     *
     * Detalle de una versión con snapshot completo (solo lectura).
     */
    public function show(string $document, string $version): JsonResponse
    {
        $doc = $this->documentService->findOrFail($document);
        $this->authorize('view', $doc);
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $v = $this->documentService->findDocumentVersionDetailOrFail($document, $version);

        return response()->json([
            'data' => $v,
        ]);
    }
}
