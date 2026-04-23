<?php

namespace App\DTOs\TemplateBlocks;

readonly class UpdateTemplateBlockDto
{
    public function __construct(
        public ?string $type = null,
        public bool $set_type = false,
        public ?string $title = null,
        public bool $set_title = false,
        public ?array $default_content = null,
        public bool $set_default_content = false,
        public ?int $sort_order = null,
        public bool $set_sort_order = false,
        public ?string $block_state = null,
        public bool $set_block_state = false,
        public ?bool $mandatory = null,
        public bool $set_mandatory = false,
        public ?array $description = null,
        public bool $set_description = false,
    ) {}
}
