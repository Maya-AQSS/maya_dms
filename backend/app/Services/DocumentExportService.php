<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\DocumentExportServiceInterface;

/**
 * Provides helpers for document export operations.
 * PDF generation is on-demand (synchronous, ephemeral) — no queue or status tracking.
 */
class DocumentExportService implements DocumentExportServiceInterface
{
    public function sanitizeFilename(string $filename): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
    }
}
