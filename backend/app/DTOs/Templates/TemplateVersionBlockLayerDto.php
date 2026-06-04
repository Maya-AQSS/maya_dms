<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

/**
 * DTO for TemplateVersionBlockLayer model.
 * Extracted from Eloquent to decouple Service from model.
 */
readonly class TemplateVersionBlockLayerDto
{
    public function __construct(
        public string $id,
        public string $entityVersionId,
        public string $templateBlockId,
        public bool $removed,
        /** @var array<string, mixed>|null */
        public ?array $overridePayload,
        public bool $inheritsFromPreviousPublication,
        public int $sortOrder,
    ) {}
}
