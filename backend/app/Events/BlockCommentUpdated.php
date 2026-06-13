<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Comment;
use App\Support\CommentAuditPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

class BlockCommentUpdated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
        public readonly string $actorId,
        public readonly string $previousBody,
    ) {}

    public function toAuditPayload(): array
    {
        $previous = CommentAuditPayload::snapshot($this->comment);
        $previous['body_excerpt'] = CommentAuditPayload::bodyExcerpt($this->previousBody);

        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => CommentAuditPayload::entityTypeFor($this->comment),
            'entityId' => (string) $this->comment->commentable_id,
            'action' => 'comment_updated',
            'userId' => $this->actorId,
            'blockId' => $this->comment->blockable_id !== null
                ? (string) $this->comment->blockable_id
                : null,
            'previousValue' => $previous,
            'newValue' => CommentAuditPayload::snapshot($this->comment),
        ];
    }
}
