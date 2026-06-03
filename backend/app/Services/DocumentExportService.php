<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\DocumentPdfExportStatusDto;
use App\Jobs\GenerateDocumentPdf;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentExportServiceInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Manages document export operations (PDF generation, status tracking, caching).
 * Encapsulates cache-based domain state and job dispatching.
 */
class DocumentExportService implements DocumentExportServiceInterface
{
    /** Cache TTL for PDF export status tracking, seconds. */
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function startPdfExport(string $documentId, string $userId): DocumentPdfExportStatusDto
    {
        // Verify document exists (throws if not found).
        $this->documentRepository->findOrFail($documentId);

        $cacheKey = GenerateDocumentPdf::keyFor($documentId);
        $current = Cache::get($cacheKey);

        // If already processing, return current state without re-enqueuing.
        if (is_array($current) && in_array($current['state'] ?? null, ['queued', 'processing'], true)) {
            return DocumentPdfExportStatusDto::fromArray($current);
        }

        $payload = [
            'state' => 'queued',
            'document_id' => $documentId,
            'queued_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $payload, self::CACHE_TTL);
        GenerateDocumentPdf::dispatch($documentId, $userId);

        return DocumentPdfExportStatusDto::fromArray($payload);
    }

    public function getPdfExportStatus(string $documentId): DocumentPdfExportStatusDto
    {
        // Verify document exists.
        $this->documentRepository->findOrFail($documentId);

        $payload = Cache::get(GenerateDocumentPdf::keyFor($documentId));

        return DocumentPdfExportStatusDto::fromArray(
            $payload ?: ['state' => 'none', 'document_id' => $documentId]
        );
    }

    public function getPdfExportPath(string $documentId): ?string
    {
        $payload = Cache::get(GenerateDocumentPdf::keyFor($documentId));

        if (! is_array($payload) || ($payload['state'] ?? null) !== 'ready' || empty($payload['path'])) {
            return null;
        }

        return (string) $payload['path'];
    }

    public function sanitizeFilename(string $filename): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
    }
}
