<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentVersionResource;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
        try {
            $doc = $this->documentService->findModelOrFail($document);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $doc = $this->documentService->findModelOrFailWithoutUserAccess($document);
            if (! $this->documentService->hasPublishedSnapshot($doc->id)) {
                abort(404);
            }
        }

        if (! Gate::forUser(Auth::user())->allows('viewHistory', $doc)) {
            abort(404);
        }
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
        try {
            $doc = $this->documentService->findModelOrFail($document);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $doc = $this->documentService->findModelOrFailWithoutUserAccess($document);
            if (! $this->documentService->hasPublishedSnapshot($doc->id)) {
                abort(404);
            }
        }

        if (! Gate::forUser(Auth::user())->allows('viewHistory', $doc)) {
            abort(404);
        }
        $this->assertOptionalProcessContextMatches((string) $doc->process_id);

        $detail = $this->documentService->findDocumentVersionDetailOrFail($document, $version);

        return (new DocumentVersionResource($detail))->response();
    }
}
