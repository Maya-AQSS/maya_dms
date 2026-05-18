<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DocumentVersion;

interface DocumentVersionRepositoryInterface
{
    public function findOrFail(string $id): DocumentVersion;

    public function findByDocumentAndVersionNumber(string $documentId, int $versionNumber): ?DocumentVersion;

    public function findOrFailByDocumentAndVersionNumber(string $documentId, int $versionNumber): DocumentVersion;
}
