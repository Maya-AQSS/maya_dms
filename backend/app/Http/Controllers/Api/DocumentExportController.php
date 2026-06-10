<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\DocumentDownloaded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ExportPdfRequest;
use App\Http\Resources\DocumentPdfExportResource;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentExportServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentExportController extends Controller
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentExportServiceInterface $exportService,
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * POST /api/v1/documents/{document}/export-pdf
     * Encola la generación del PDF/UA. Idempotente: si ya hay un job en curso
     * para este documento, devuelve el estado actual sin reencolar.
     */
    public function start(ExportPdfRequest $request, string $document): JsonResponse
    {
        $model = $this->documentRepository->findOrFail($document);
        $this->authorize('view', $model);

        $userId = (string) $request->user()->getAuthIdentifier();
        $status = $this->exportService->startPdfExport($document, $userId);

        return response()->json(
            ['data' => new DocumentPdfExportResource($status)],
            Response::HTTP_ACCEPTED
        );
    }

    /**
     * GET /api/v1/documents/{document}/export-status
     * Devuelve el estado actual del job. Estados: queued | processing | ready | failed | none.
     */
    public function status(string $document): JsonResponse
    {
        $this->documentRepository->findOrFail($document);

        $status = $this->exportService->getPdfExportStatus($document);

        return response()->json([
            'data' => new DocumentPdfExportResource($status),
        ]);
    }

    /**
     * GET /api/v1/documents/{document}/pdf
     * Descarga el último PDF generado (si existe).
     */
    public function download(Request $request, string $document): BinaryFileResponse
    {
        $model = $this->documentRepository->findOrFail($document);
        $this->authorize('view', $model);

        $path = $this->exportService->getPdfExportPath($document);
        if ($path === null) {
            abort(404, 'No hay PDF generado para este documento. Solicita un export primero.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404, 'El PDF generado ya no está disponible. Solicita un export nuevo.');
        }

        $this->recordDownload($request, $document);

        $filename = $this->exportService->sanitizeFilename((string) ($model->title ?? 'documento')).'.pdf';

        return response()->download(
            $disk->path($path),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * POST /api/v1/documents/{document}/versions/{version}/export-pdf
     * Encola la generación del PDF de una versión histórica (snapshot congelado).
     */
    public function startVersion(ExportPdfRequest $request, string $document, string $version): JsonResponse
    {
        $this->resolveDocumentForHistory($document);
        $this->assertVersionBelongs($document, $version);

        $userId = (string) $request->user()->getAuthIdentifier();
        $status = $this->exportService->startPdfExport($document, $userId, $version);

        return response()->json(
            ['data' => new DocumentPdfExportResource($status)],
            Response::HTTP_ACCEPTED
        );
    }

    /**
     * GET /api/v1/documents/{document}/versions/{version}/export-status
     */
    public function statusVersion(string $document, string $version): JsonResponse
    {
        $this->resolveDocumentForHistory($document);

        $status = $this->exportService->getPdfExportStatus($document, $version);

        return response()->json([
            'data' => new DocumentPdfExportResource($status),
        ]);
    }

    /**
     * GET /api/v1/documents/{document}/versions/{version}/pdf
     * Descarga el PDF de una versión histórica (si existe).
     */
    public function downloadVersion(Request $request, string $document, string $version): BinaryFileResponse
    {
        $this->resolveDocumentForHistory($document);
        $detail = $this->assertVersionBelongs($document, $version);

        $path = $this->exportService->getPdfExportPath($document, $version);
        if ($path === null) {
            abort(404, 'No hay PDF generado para esta versión. Solicita un export primero.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404, 'El PDF generado ya no está disponible. Solicita un export nuevo.');
        }

        $versionNumber = (int) ($detail['version_number'] ?? 0);
        $this->recordDownload($request, $document, $version, $versionNumber);

        $base = $this->exportService->sanitizeFilename((string) ($detail['snapshot_data']['document']['title'] ?? 'documento'));
        $filename = $base.'_v'.$versionNumber.'.pdf';

        return response()->download(
            $disk->path($path),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Resuelve el documento aplicando el mismo patrón que DocumentVersionController:
     * acceso normal o, para documentos publicados por otros, acceso por snapshot
     * publicado; siempre tras superar el gate `viewHistory`.
     */
    private function resolveDocumentForHistory(string $document): Document
    {
        try {
            $doc = $this->documentService->findModelOrFail($document);
        } catch (ModelNotFoundException) {
            $doc = $this->documentService->findModelOrFailWithoutUserAccess($document);
            if (! $this->documentService->hasPublishedSnapshot($doc->id)) {
                abort(404);
            }
        }

        if (! Gate::forUser(Auth::user())->allows('viewHistory', $doc)) {
            abort(404);
        }

        return $doc;
    }

    /**
     * Valida que la versión pertenece al documento (lanza 404 si no) y devuelve
     * su detalle resuelto.
     *
     * @return array<string, mixed>
     */
    private function assertVersionBelongs(string $document, string $version): array
    {
        return $this->documentService->findDocumentVersionDetailOrFail($document, $version);
    }

    private function recordDownload(
        Request $request,
        string $documentId,
        ?string $versionId = null,
        ?int $versionNumber = null,
    ): void {
        DocumentDownloaded::dispatch(
            $documentId,
            (string) $request->user()?->getAuthIdentifier(),
            'pdf',
            $versionId,
            $versionNumber,
            $request->ip(),
            $request->userAgent(),
        );
    }
}
