<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Template;

final class CommentAuditPayload
{
    public static function entityTypeFor(Comment $comment): string
    {
        return self::entityTypeForClass((string) $comment->commentable_type);
    }

    public static function entityTypeForClass(string $commentableType): string
    {
        return match ($commentableType) {
            Template::class => 'template',
            Document::class => 'document',
            default => 'unknown',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Comment $comment): array
    {
        return array_filter(
            [
                'comment_id' => (string) $comment->id,
                'commentable_version' => (int) $comment->commentable_version,
                'parent_id' => $comment->parent_id,
                'author_id' => (string) $comment->author_id,
                'body_excerpt' => self::bodyExcerpt($comment->body),
            ],
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    public static function bodyExcerpt(?string $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        return mb_strlen($body) > 200 ? mb_substr($body, 0, 200).'…' : $body;
    }
}
