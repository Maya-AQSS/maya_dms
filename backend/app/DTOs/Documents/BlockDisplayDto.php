<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Información de un bloque para visualización/edición en el cliente.
 * Combina definición (template) + estado (documento).
 */
readonly class BlockDisplayDto
{
    public function __construct(
        public ?string $document_block_id,
        public string $template_block_id,
        public string $type,
        public ?string $title,
        /** TipTap JSON from template block definition, or legacy plain text. */
        public mixed $description,
        public mixed $default_content,
        public string $block_state,
        public bool $mandatory,
        public int $sort_order,
        public mixed $content,
        public bool $is_filled,
        public bool $is_deleted,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_block_id' => $this->document_block_id,
            'template_block_id' => $this->template_block_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'default_content' => $this->default_content,
            'block_state' => $this->block_state,
            'mandatory' => $this->mandatory,
            'sort_order' => $this->sort_order,
            'content' => $this->content,
            'is_filled' => $this->is_filled,
            'is_deleted' => $this->is_deleted,
        ];
    }
}
