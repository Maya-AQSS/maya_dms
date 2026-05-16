<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Template;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

class TemplateStateChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Template $template,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $actorId,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => 'template',
            'entityId' => (string) $this->template->id,
            'action' => 'state_changed',
            'userId' => $this->actorId,
            'previousValue' => ['status' => $this->oldStatus],
            'newValue' => ['status' => $this->newStatus],
        ];
    }
}
