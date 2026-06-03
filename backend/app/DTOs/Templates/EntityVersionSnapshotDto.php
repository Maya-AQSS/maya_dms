<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

/**
 * Snapshot data for entity version (extracted from Eloquent model).
 * Passed to Services to avoid model coupling.
 */
readonly class EntityVersionSnapshotDto
{
    public function __construct(
        public string $id,
        public string $entityId,
        public int $versionNumber,
        /** @var list<array<string, mixed>> */
        public array $blocksSnapshotRows,
    ) {}
}
