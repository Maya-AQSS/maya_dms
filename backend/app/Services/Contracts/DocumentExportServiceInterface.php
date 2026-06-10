<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Documents\DocumentPdfExportStatusDto;

interface DocumentExportServiceInterface
{
    /**
     * Starts a PDF export job for the given document (or one of its historical
     * versions when `$versionId` is provided).
     * Idempotent: if a job is already queued/processing, returns its current state without re-enqueuing.
     */
    public function startPdfExport(string $documentId, string $userId, ?string $versionId = null): DocumentPdfExportStatusDto;

    /**
     * Gets the current export status for the given document (or version).
     */
    public function getPdfExportStatus(string $documentId, ?string $versionId = null): DocumentPdfExportStatusDto;

    /**
     * Gets the relative path to a ready PDF export, or null if not available.
     */
    public function getPdfExportPath(string $documentId, ?string $versionId = null): ?string;

    /**
     * Sanitizes a filename for safe use in HTTP responses.
     */
    public function sanitizeFilename(string $filename): string;
}
