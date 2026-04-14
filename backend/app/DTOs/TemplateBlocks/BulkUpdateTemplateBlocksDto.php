<?php

namespace App\DTOs\TemplateBlocks;

readonly class BulkUpdateTemplateBlocksDto
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        public array $ids,
        public string $block_state,
        public ?bool $mandatory = null,
        public bool $set_mandatory = false,
    ) {}
}
