<?php

declare(strict_types=1);

namespace App\DTOs\TemplateBlocks;

readonly class BulkUpdateTemplateBlocksDto
{
    /**
     * @param  list<string>  $ids
     */
    public function __construct(
        public array $ids,
        public ?string $blockState = null,
        public bool $setBlockState = false,
    ) {}
}
