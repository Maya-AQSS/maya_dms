<?php

declare(strict_types=1);

namespace App\DTOs\AnchoredComment;

use App\Models\AnchoredComment;

final readonly class AnchoredCommentDto
{
    public function __construct(
        public string $id,
        public string $commentId,
        public string $resourceType,
        public string $resourceId,
        public int $anchorFrom,
        public int $anchorTo,
        public ?string $anchorTextSnapshot,
        public bool $anchorIsValid,
        public ?string $anchorLastSyncedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
        public AnchoredComment $source,
    ) {}

    public static function fromModel(AnchoredComment $m): self
    {
        return new self(
            id: (string) $m->id,
            commentId: (string) $m->comment_id,
            resourceType: (string) $m->resource_type,
            resourceId: (string) $m->resource_id,
            anchorFrom: (int) $m->anchor_from,
            anchorTo: (int) $m->anchor_to,
            anchorTextSnapshot: $m->anchor_text_snapshot,
            anchorIsValid: (bool) $m->anchor_is_valid,
            anchorLastSyncedAt: $m->anchor_last_synced_at?->toIso8601String(),
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            source: $m,
        );
    }
}
