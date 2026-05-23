<?php

declare(strict_types=1);

namespace App\DTOs\TemplateBlocks;

use App\Enums\BlockKind;
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
        public string $kind,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(TemplateBlock $m): self
    {
        $state = $m->block_state;
        $kind = $m->kind;

        return new self(
            id: (string) $m->id,
            templateId: (string) $m->template_id,
            title: $m->title,
            defaultContent: $m->default_content,
            description: $m->description,
            blockState: $state instanceof BackedEnum ? (string) $state->value : ($state !== null ? (string) $state : null),
            sortOrder: (int) $m->sort_order,
            kind: $kind instanceof BlockKind ? $kind->value : (is_string($kind) && $kind !== '' ? $kind : BlockKind::Content->value),
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
        );
    }
}
