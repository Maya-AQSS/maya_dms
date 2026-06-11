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
use App\Services\Contracts\DocumentPdfServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DocumentExportController extends Controller
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentExportServiceInterface $exportService,
        private readonly DocumentServiceInterface $documentService,
        private readonly DocumentPdfServiceInterface $pdfService,
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
     * Genera y descarga el PDF/UA del documento de forma SÍNCRONA (WeasyPrint a
     * memoria, mismo patrón que el PDF de muestra de themes): sin cola ni polling.
     */
    public function download(Request $request, string $document): Response
    {
        $model = $this->documentRepository->findOrFail($document);
        $this->authorize('view', $model);

        $bytes = $this->pdfService->generateBytes($document);
        $this->recordDownload($request, $document);

        $filename = $this->exportService->sanitizeFilename((string) ($model->title ?? 'documento')).'.pdf';

        return $this->pdfResponse($bytes, $filename);
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
     * Genera y descarga el PDF de una versión histórica (snapshot congelado) de
     * forma SÍNCRONA. Gate viewHistory + validación de pertenencia de la versión.
     */
    public function downloadVersion(Request $request, string $document, string $version): Response
    {
        $this->resolveDocumentForHistory($document);
        $detail = $this->assertVersionBelongs($document, $version);

        $bytes = $this->pdfService->generateBytes($document, $version);

        $versionNumber = (int) ($detail['version_number'] ?? 0);
        $this->recordDownload($request, $document, $version, $versionNumber);

        $base = $this->exportService->sanitizeFilename((string) ($detail['snapshot_data']['document']['title'] ?? 'documento'));
        $filename = $base.'_v'.$versionNumber.'.pdf';

        return $this->pdfResponse($bytes, $filename);
    }

    /**
     * Resuelve el documento aplicando el mismo patrón que DocumentVersionController:
     * acceso normal o, para documentos publicados por otros, acceso por snapshot
     * publicado; siempre tras superar el gate `viewHistory`.
     */
    private function resolveDocumentForHistory(string $document): Document
    {
        $doc = $this->documentService->resolveDocumentWithPublishedFallback($document);

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

    private function pdfResponse(string $bytes, string $filename): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
