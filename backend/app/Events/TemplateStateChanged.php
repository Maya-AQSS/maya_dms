<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Template;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

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
        $name = (string) ($this->template->name ?? '');
        $label = $name !== '' ? "'{$name}'" : 'plantilla';
        $byReviewer = $this->reviewerName ? " por {$this->reviewerName}" : '';
        $atStage = $this->reviewerStage !== null ? " (etapa {$this->reviewerStage})" : '';

        $description = match (true) {
            $this->newStatus === 'rejected' => "Plantilla {$label} rechazada{$atStage}{$byReviewer}",
            $this->newStatus === 'published' => "Plantilla {$label} publicada{$atStage}{$byReviewer}",
            default => "Estado de plantilla {$label} cambiado de '{$this->oldStatus}' a '{$this->newStatus}'",
        };

        $context = array_filter([
            'description' => $description,
            'template_name' => $name !== '' ? $name : null,
            'reviewer_name' => $this->reviewerName,
            'reviewer_stage' => $this->reviewerStage,
        ], static fn ($v): bool => $v !== null);

        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'template',
            'entityId' => (string) $this->template->id,
            'action' => 'state_changed',
            'userId' => $this->actorId,
            'previousValue' => ['status' => $this->oldStatus],
            'newValue' => array_filter([
                'status' => $this->newStatus,
                'stage' => $this->reviewerStage,
                'reviewer_name' => $this->reviewerName,
                '_context' => $context,
            ], static fn ($v): bool => $v !== null),
        ];
    }
}
