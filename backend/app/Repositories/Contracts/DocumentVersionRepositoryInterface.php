<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Documents\DocumentVersionSnapshotDto;
use App\Models\DocumentVersion;

interface DocumentVersionRepositoryInterface
{
    public function findOrFail(string $id): DocumentVersion;

    public function findByDocumentAndVersionNumber(string $documentId, int $versionNumber): ?DocumentVersion;

    public function findOrFailByDocumentAndVersionNumber(string $documentId, int $versionNumber): DocumentVersion;

    public function findOrFailAsSnapshot(string $id): DocumentVersionSnapshotDto;

    public function findByDocumentAndVersionNumberAsSnapshot(string $documentId, int $versionNumber): ?DocumentVersionSnapshotDto;

    public function findOrFailByDocumentAndVersionNumberAsSnapshot(string $documentId, int $versionNumber): DocumentVersionSnapshotDto;
}
