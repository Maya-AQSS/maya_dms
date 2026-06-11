<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface DocumentExportServiceInterface
{
    /**
     * Sanitizes a filename for safe use in HTTP responses.
     */
    public function sanitizeFilename(string $filename): string;
}
