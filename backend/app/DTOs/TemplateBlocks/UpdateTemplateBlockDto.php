<?php

namespace App\DTOs\TemplateBlocks;

readonly class UpdateTemplateBlockDto
{
    public function __construct(
        public ?string $title = null,
        public bool $set_title = false,
        public ?array $default_content = null,
        public bool $set_default_content = false,
        public ?int $sort_order = null,
        public bool $set_sort_order = false,
        public ?string $block_state = null,
        public bool $set_block_state = false,
        public ?string $description = null,
        public bool $set_description = false,
    ) {}
}
