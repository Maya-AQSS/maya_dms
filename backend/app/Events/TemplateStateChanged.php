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
        $stageInfo = $this->reviewerStage !== null
            ? trans('audit.at_stage', ['stage' => $this->reviewerStage], 'es')
            : '';
        $byInfo = $this->reviewerName
            ? trans('audit.by_reviewer', ['reviewer' => $this->reviewerName], 'es')
            : '';

        $key = match (true) {
            $this->newStatus === 'rejected' => 'audit.template.state_changed.rejected',
            $this->newStatus === 'published' => 'audit.template.state_changed.published',
            default => 'audit.template.state_changed.default',
        };

        $context = array_filter([
            'description' => trans($key, [
                'name' => $name ?: trans('audit.unnamed', [], 'es'),
                'stage_info' => $stageInfo,
                'by_info' => $byInfo,
                'old' => $this->oldStatus,
                'new' => $this->newStatus,
            ], 'es'),
            'template_name' => $name !== '' ? $name : null,
            'reviewer_name' => $this->reviewerName,
            'reviewer_stage' => $this->reviewerStage,
            'url' => $this->newStatus === 'in_review'
                ? "/templates/{$this->template->id}/review"
                : "/templates/{$this->template->id}",
            'target_app' => 'dms',
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
