<?php

namespace App\Listeners;

use App\Events\DocumentStateChanged;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RecordDocumentStateChange implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogServiceInterface $auditLogService,
    ) {}

    public function handle(DocumentStateChanged $event): void
    {
        $this->auditLogService->record(
            entityType:    'document',
            entityId:      $event->document->id,
            action:        'state_changed',
            userId:        $event->actorId,
            previousValue: ['status' => $event->oldStatus],
            newValue:      ['status' => $event->newStatus],
        );
    }
}
