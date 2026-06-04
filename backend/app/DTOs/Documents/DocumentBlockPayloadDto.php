<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Block payload extracted from DocumentBlock model.
 * Passed to Services to avoid model coupling.
 */
readonly class DocumentBlockPayloadDto
{
    public function __construct(
        public string $blockId,
        public ?string $templateBlockId,
        public mixed $content,
        public bool $isFilled,
        public int $sortOrder,
        public ?string $lastEditedBy,
        public ?string $lockedBy,
        public ?string $lockedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->blockId,
            'template_block_id' => $this->templateBlockId,
            'content' => $this->content,
            'is_filled' => $this->isFilled,
            'sort_order' => $this->sortOrder,
            'last_edited_by' => $this->lastEditedBy,
            'locked_by' => $this->lockedBy,
            'locked_at' => $this->lockedAt,
        ];
    }
}
