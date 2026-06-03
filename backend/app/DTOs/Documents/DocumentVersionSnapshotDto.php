<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Snapshot data for document version (extracted from Eloquent model).
 * Passed to Services to avoid model coupling.
 */
readonly class DocumentVersionSnapshotDto
{
    public function __construct(
        public string $id,
        public string $documentId,
        public int $versionNumber,
        /** @var array<string, mixed> */
        public array $snapshotData,
    ) {}
}
