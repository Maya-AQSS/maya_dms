<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;

class DocumentVersionController extends Controller
{
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

        $rows = $doc->versions()
            ->orderByDesc('version_number')
            ->get()
            ->map(static function (DocumentVersion $v): array {
                return [
                    'id' => $v->id,
                    'document_id' => $v->document_id,
                    'version_number' => $v->version_number,
                    'trigger_event' => $v->trigger_event,
                    'triggered_by' => $v->triggered_by,
                    'changelog' => $v->notes,
                    'notes' => $v->notes,
                    'created_at' => $v->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

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

        $v = $this->documentService->findDocumentVersionOrFail($document, $version);

        return response()->json([
            'data' => [
                'id' => $v->id,
                'document_id' => $v->document_id,
                'version_number' => $v->version_number,
                'trigger_event' => $v->trigger_event,
                'triggered_by' => $v->triggered_by,
                'changelog' => $v->notes,
                'snapshot_data' => $v->snapshot_data,
                'created_at' => $v->created_at?->toIso8601String(),
            ],
        ]);
    }
}
