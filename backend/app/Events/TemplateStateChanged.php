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
        public readonly ?int $reviewerStage = null,
        public readonly ?string $reviewerName = null,
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
            // Cuando la transición la provoca la decisión de un validador (rechazo o
            // aprobación final que publica), adjuntamos su etapa y nombre; en el resto
            // de transiciones quedan ausentes.
            'newValue' => array_filter([
                'status' => $this->newStatus,
                'stage' => $this->reviewerStage,
                'reviewer_name' => $this->reviewerName,
            ], static fn ($v): bool => $v !== null),
        ];
    }
}
