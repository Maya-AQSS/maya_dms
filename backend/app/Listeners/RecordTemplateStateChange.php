<?php

namespace App\Listeners;

use App\Events\TemplateStateChanged;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Maya\Messaging\Publishers\AuditPublisher;

class RecordTemplateStateChange implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditPublisher $auditPublisher,
    ) {}

    public function handle(TemplateStateChanged $event): void
    {
        $this->auditPublisher->publish(
            applicationSlug: 'maya-dms',
            entityType:      'template',
            entityId:        $event->template->id,
            action:          'state_changed',
            userId:          $event->actorId,
            previousValue:   ['status' => $event->oldStatus],
            newValue:        ['status' => $event->newStatus],
        );
    }
}
