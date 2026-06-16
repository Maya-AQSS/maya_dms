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
        $title = (string) ($this->document->title ?? '');
        $stageInfo = $this->reviewerStage !== null
            ? trans('audit.at_stage', ['stage' => $this->reviewerStage], 'es')
            : '';
        $byInfo = $this->reviewerName
            ? trans('audit.by_reviewer', ['reviewer' => $this->reviewerName], 'es')
            : '';

        $key = match (true) {
            $this->newStatus === 'rejected' => 'audit.document.state_changed.rejected',
            $this->newStatus === 'published' => 'audit.document.state_changed.published',
            default => 'audit.document.state_changed.default',
        };

        $context = array_filter([
            'description' => trans($key, [
                'title' => $title ?: trans('audit.unnamed', [], 'es'),
                'stage_info' => $stageInfo,
                'by_info' => $byInfo,
                'old' => $this->oldStatus,
                'new' => $this->newStatus,
            ], 'es'),
            'document_title' => $title !== '' ? $title : null,
            'reviewer_name' => $this->reviewerName,
            'reviewer_stage' => $this->reviewerStage,
            'rejection_reason' => $this->rejectionReason,
        ], static fn ($v): bool => $v !== null);

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
                '_context' => $context,
            ], static fn ($v): bool => $v !== null),
        ];
    }
}
