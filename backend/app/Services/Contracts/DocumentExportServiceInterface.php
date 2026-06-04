<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Documents\DocumentPdfExportStatusDto;

interface DocumentExportServiceInterface
{
    /**
     * Starts a PDF export job for the given document.
     * Idempotent: if a job is already queued/processing, returns its current state without re-enqueuing.
     */
    public function startPdfExport(string $documentId, string $userId): DocumentPdfExportStatusDto;

    /**
     * Gets the current export status for the given document.
     */
    public function getPdfExportStatus(string $documentId): DocumentPdfExportStatusDto;

    /**
     * Gets the relative path to a ready PDF export, or null if not available.
     */
    public function getPdfExportPath(string $documentId): ?string;

    /**
     * Sanitizes a filename for safe use in HTTP responses.
     */
    public function sanitizeFilename(string $filename): string;
}
