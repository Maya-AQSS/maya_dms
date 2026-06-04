<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ExportPdfRequest;
use App\Http\Resources\DocumentPdfExportResource;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentExportServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentExportController extends Controller
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentExportServiceInterface $exportService,
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
    public function download(string $document): BinaryFileResponse
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

        $filename = $this->exportService->sanitizeFilename((string) ($model->title ?? 'documento')).'.pdf';

        return response()->download(
            $disk->path($path),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
