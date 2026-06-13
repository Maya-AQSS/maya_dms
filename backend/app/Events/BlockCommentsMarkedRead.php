<?php

declare(strict_types=1);

namespace App\Events;

use App\Support\CommentAuditPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Hecho de negocio: un usuario marca como leídos varios comentarios de un bloque.
 */
class BlockCommentsMarkedRead implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $commentableType,
        public readonly string $commentableId,
        public readonly int $commentableVersion,
        public readonly string $blockableType,
        public readonly string $blockableId,
        public readonly string $readerUserId,
        public readonly int $markedCount,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => CommentAuditPayload::entityTypeForClass($this->commentableType),
            'entityId' => $this->commentableId,
            'action' => 'comments_marked_read',
            'userId' => $this->readerUserId,
            'blockId' => $this->blockableId,
            'newValue' => [
                'commentable_version' => $this->commentableVersion,
                'marked_count' => $this->markedCount,
            ],
        ];
    }
}
