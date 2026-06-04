<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

/**
 * Block payload extracted from TemplateBlock model.
 * Passed to Services to avoid model coupling.
 */
readonly class TemplateBlockPayloadDto
{
    public function __construct(
        public string $blockId,
        public string $title,
        /** TipTap JSON ({@see TemplateBlock::$casts}) or legacy plain text. */
        public mixed $description,
        public mixed $defaultContent,
        public mixed $blockState,
        public int $sortOrder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $state = $this->blockState instanceof \BackedEnum ? $this->blockState->value : $this->blockState;

        return [
            'id' => $this->blockId,
            'title' => $this->title,
            'description' => $this->description,
            'default_content' => $this->defaultContent,
            'block_state' => $state,
            'sort_order' => $this->sortOrder,
        ];
    }
}
