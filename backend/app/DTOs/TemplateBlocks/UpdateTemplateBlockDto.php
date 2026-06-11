<?php

declare(strict_types=1);

namespace App\DTOs\TemplateBlocks;

readonly class UpdateTemplateBlockDto
{
    public function __construct(
        public ?string $title = null,
        public bool $setTitle = false,
        public ?array $defaultContent = null,
        public bool $setDefaultContent = false,
        public ?int $sortOrder = null,
        public bool $setSortOrder = false,
        public ?string $blockState = null,
        public bool $setBlockState = false,
        public ?array $description = null,
        public bool $setDescription = false,
        public ?string $blockType = null,
        public bool $setBlockType = false,
        public ?bool $pageBreakAfter = null,
        public bool $setPageBreakAfter = false,
        public ?string $themeId = null,
        public bool $setThemeId = false,
        public ?bool $applyTheme = null,
        public bool $setApplyTheme = false,
    ) {}
}
