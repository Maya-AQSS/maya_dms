<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * DTO for DocumentVersionBlockLayer model.
 * Extracted from Eloquent to decouple Service from model.
 */
readonly class DocumentVersionBlockLayerDto
{
    public function __construct(
        public string $id,
        public string $documentVersionId,
        public string $documentBlockId,
        public bool $removed,
        /** @var array<string, mixed>|null */
        public ?array $overridePayload,
        public bool $inheritsFromPreviousPublication,
        public int $sortOrder,
    ) {}
}
