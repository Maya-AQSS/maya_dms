<?php

declare(strict_types=1);

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
        public ?array $description = null,
        public bool $set_description = false,
        public ?string $block_type = null,
        public bool $set_block_type = false,
        public ?bool $page_break_after = null,
        public bool $set_page_break_after = false,
        public ?string $theme_id = null,
        public bool $set_theme_id = false,
        public ?bool $apply_theme = null,
        public bool $set_apply_theme = false,
    ) {}
}
