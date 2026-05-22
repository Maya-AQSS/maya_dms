<?php

declare(strict_types=1);

namespace App\DTOs\Comments;

use App\DTOs\Refs\UserRefDto;
use App\Models\Comment;

final readonly class CommentDto
{
    public function __construct(
        public string $id,
        public string $commentableType,
        public string $commentableId,
        public ?int $commentableVersion,
        public ?string $blockableType,
        public ?string $blockableId,
        public ?string $parentId,
        public ?string $authorId,
        public ?UserRefDto $author,
        public bool $authorLoaded,
        public string $body,
        public bool $resolved,
        public ?string $resolvedBy,
        public ?string $resolvedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
        public bool $isEdited,
        public bool $isDeleted,
        public ?string $deletedAt,
        public ?string $deletedByName,
        // Source model retained for policy gates that need an Eloquent instance.
        public Comment $source,
    ) {}

    public static function fromModel(Comment $m): self
    {
        $authorLoaded = $m->relationLoaded('author');

        return new self(
            id: (string) $m->id,
            commentableType: (string) $m->commentable_type,
            commentableId: (string) $m->commentable_id,
            commentableVersion: $m->commentable_version !== null ? (int) $m->commentable_version : null,
            blockableType: $m->blockable_type,
            blockableId: $m->blockable_id !== null ? (string) $m->blockable_id : null,
            parentId: $m->parent_id !== null ? (string) $m->parent_id : null,
            authorId: $m->author_id !== null ? (string) $m->author_id : null,
            author: $authorLoaded && $m->author !== null
                ? new UserRefDto(id: (string) $m->author->id, name: $m->author->name)
                : null,
            authorLoaded: $authorLoaded,
            body: (string) $m->body,
            resolved: (bool) $m->resolved,
            resolvedBy: $m->resolved_by !== null ? (string) $m->resolved_by : null,
            resolvedAt: $m->resolved_at?->toIso8601String(),
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            isEdited: $m->updated_at !== null,
            isDeleted: $m->trashed(),
            deletedAt: $m->deleted_at?->toIso8601String(),
            deletedByName: $m->deleted_by_name,
            source: $m,
        );
    }
}
