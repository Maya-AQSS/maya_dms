<?php

namespace App\DTOs\TemplateBlocks;

readonly class UpdateTemplateBlockDto
{
    public function __construct(
        public ?string $block_state = null,
        public bool $set_block_state = false,
        public ?bool $mandatory = null,
        public bool $set_mandatory = false,
    ) {}
}
