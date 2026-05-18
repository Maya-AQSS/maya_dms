<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

class DocumentStateChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Document $document,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $actorId,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => 'document',
            'entityId' => (string) $this->document->id,
            'action' => 'state_changed',
            'userId' => $this->actorId,
            'previousValue' => ['status' => $this->oldStatus],
            'newValue' => ['status' => $this->newStatus],
        ];
    }
}
