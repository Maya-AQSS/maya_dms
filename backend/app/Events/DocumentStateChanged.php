<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

class DocumentStateChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Document $document,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $actorId,
        public readonly ?int $reviewerStage = null,
        public readonly ?string $reviewerName = null,
        public readonly ?string $rejectionReason = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'document',
            'entityId' => (string) $this->document->id,
            'action' => 'state_changed',
            'userId' => $this->actorId,
            'previousValue' => ['status' => $this->oldStatus],
            'newValue' => array_filter([
                'status' => $this->newStatus,
                'stage' => $this->reviewerStage,
                'reviewer_name' => $this->reviewerName,
                'rejection_reason' => $this->rejectionReason,
            ], static fn ($v): bool => $v !== null),
        ];
    }
}
