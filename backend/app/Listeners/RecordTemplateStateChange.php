<?php

namespace App\Listeners;

use App\Events\TemplateStateChanged;
use App\Services\AuditLogService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RecordTemplateStateChange implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function handle(TemplateStateChanged $event): void
    {
        $this->auditLogService->record(
            entityType:    'template',
            entityId:      $event->template->id,
            action:        'state_changed',
            userId:        $event->actorId,
            previousValue: ['status' => $event->oldStatus],
            newValue:      ['status' => $event->newStatus],
        );
    }
}
