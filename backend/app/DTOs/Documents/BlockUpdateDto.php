<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Resultado de una actualización de bloque de documento.
 */
readonly class BlockUpdateDto
{
    public function __construct(
        public string $documentBlockId,
        public string $templateBlockId,
        public mixed $content,
        public bool $isFilled,
        public string $lastEditedBy,
        public ?string $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_block_id' => $this->documentBlockId,
            'template_block_id' => $this->templateBlockId,
            'content' => $this->content,
            'is_filled' => $this->isFilled,
            'last_edited_by' => $this->lastEditedBy,
            'updated_at' => $this->updatedAt,
        ];
    }
}
