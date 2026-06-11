<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

/**
 * Generic DTO for a version block layer row, shared across
 * template (entity_versions + template_blocks) and document
 * (document_versions + document_blocks) domains.
 *
 * Generic field names:
 *   - versionId  → entity_version_id (template) | document_version_id (document)
 *   - blockId    → template_block_id (template)  | document_block_id  (document)
 */
readonly class VersionBlockLayerDto
{
    public function __construct(
        public string $id,
        public string $versionId,
        public string $blockId,
        public bool $removed,
        /** @var array<string, mixed>|null */
        public ?array $overridePayload,
        public bool $inheritsFromPreviousPublication,
        public int $sortOrder,
    ) {}
}
