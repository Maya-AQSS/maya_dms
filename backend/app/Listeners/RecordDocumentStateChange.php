<?php

namespace App\Listeners;

use App\Events\DocumentStateChanged;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Maya\Messaging\Publishers\AuditPublisher;

class RecordDocumentStateChange implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditPublisher $auditPublisher,
    ) {}

    public function handle(DocumentStateChanged $event): void
    {
        $this->auditPublisher->publish(
            applicationSlug: 'maya-dms',
            entityType:      'document',
            entityId:        $event->document->id,
            action:          'state_changed',
            userId:          $event->actorId,
            previousValue:   ['status' => $event->oldStatus],
            newValue:        ['status' => $event->newStatus],
        );
    }
}
