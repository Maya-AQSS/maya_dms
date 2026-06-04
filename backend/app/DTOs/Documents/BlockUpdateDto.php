<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Resultado de una actualización de bloque de documento.
 */
readonly class BlockUpdateDto
{
    public function __construct(
        public string $document_block_id,
        public string $template_block_id,
        public mixed $content,
        public bool $is_filled,
        public string $last_edited_by,
        public ?string $updated_at,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_block_id' => $this->document_block_id,
            'template_block_id' => $this->template_block_id,
            'content' => $this->content,
            'is_filled' => $this->is_filled,
            'last_edited_by' => $this->last_edited_by,
            'updated_at' => $this->updated_at,
        ];
    }
}
