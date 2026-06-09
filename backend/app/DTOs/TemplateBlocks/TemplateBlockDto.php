<?php

declare(strict_types=1);

namespace App\DTOs\TemplateBlocks;

use App\Models\TemplateBlock;
use BackedEnum;

final readonly class TemplateBlockDto
{
    public function __construct(
        public string $id,
        public string $templateId,
        public ?string $title,
        public mixed $defaultContent,
        public mixed $description,
        public ?string $blockState,
        public int $sortOrder,
        public ?string $createdAt,
        public ?string $updatedAt,
        public string $blockType = 'content',
        public bool $pageBreakAfter = false,
        public ?string $themeId = null,
        public bool $applyTheme = true,
    ) {}

    public static function fromModel(TemplateBlock $m): self
    {
        $state = $m->block_state;
        $type = $m->block_type;

        return new self(
            id: (string) $m->id,
            templateId: (string) $m->template_id,
            title: $m->title,
            defaultContent: $m->default_content,
            description: $m->description,
            blockState: $state instanceof BackedEnum ? (string) $state->value : ($state !== null ? (string) $state : null),
            sortOrder: (int) $m->sort_order,
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            blockType: $type instanceof BackedEnum ? (string) $type->value : ($type !== null ? (string) $type : 'content'),
            pageBreakAfter: (bool) $m->page_break_after,
            themeId: $m->theme_id !== null ? (string) $m->theme_id : null,
            applyTheme: (bool) $m->apply_theme,
        );
    }
}
