<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDocumentPdf;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentExportController extends Controller
{
    /**
     * POST /api/v1/documents/{document}/export-pdf
     * Encola la generación del PDF/UA. Idempotente: si ya hay un job en curso
     * para este documento, devuelve el estado actual sin reencolar.
     */
    public function start(Request $request, string $document): JsonResponse
    {
        $model = Document::query()->findOrFail($document);
        $this->authorize('view', $model);

        $userId = (string) $request->user()->getAuthIdentifier();
        $cacheKey = GenerateDocumentPdf::keyFor($document);
        $current = Cache::get($cacheKey);

        // Si ya está procesándose, no encolamos otra vez.
        if (is_array($current) && in_array($current['state'] ?? null, ['queued', 'processing'], true)) {
            return response()->json(['data' => $current], Response::HTTP_ACCEPTED);
        }

        Cache::put($cacheKey, [
            'state' => 'queued',
            'document_id' => $document,
            'queued_at' => now()->toIso8601String(),
        ], 1800);

        GenerateDocumentPdf::dispatch($document, $userId);

        return response()->json([
            'data' => [
                'state' => 'queued',
                'document_id' => $document,
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/v1/documents/{document}/export-status
     * Devuelve el estado actual del job. Estados: queued | processing | ready | failed | none.
     */
    public function status(string $document): JsonResponse
    {
        Document::query()->findOrFail($document);

        $payload = Cache::get(GenerateDocumentPdf::keyFor($document));

        return response()->json([
            'data' => $payload ?: ['state' => 'none', 'document_id' => $document],
        ]);
    }

    /**
     * GET /api/v1/documents/{document}/pdf
     * Descarga el último PDF generado (si existe).
     */
    public function download(string $document): BinaryFileResponse
    {
        $model = Document::query()->findOrFail($document);
        $this->authorize('view', $model);

        $payload = Cache::get(GenerateDocumentPdf::keyFor($document));
        if (! is_array($payload) || ($payload['state'] ?? null) !== 'ready' || empty($payload['path'])) {
            abort(404, 'No hay PDF generado para este documento. Solicita un export primero.');
        }

        $relative = (string) $payload['path'];
        $disk = Storage::disk('local');
        if (! $disk->exists($relative)) {
            abort(404, 'El PDF generado ya no está disponible. Solicita un export nuevo.');
        }

        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) ($model->name ?? 'documento')).'.pdf';

        return response()->download(
            $disk->path($relative),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
