<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TemplateReviewer;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Hecho de negocio: un revisor asignado aprueba su etapa de la plantilla.
 */
class TemplateReviewApproved implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $templateId,
        public readonly TemplateReviewer $reviewer,
        public readonly string $actorId,
        public readonly ?string $reviewerName = null,
        public readonly ?string $templateName = null,
    ) {}

    public function toAuditPayload(): array
    {
        $stage = (int) $this->reviewer->stage;
        $byInfo = $this->reviewerName
            ? trans('audit.by_reviewer', ['reviewer' => $this->reviewerName], 'es')
            : '';

        $context = array_filter([
            'description' => trans('audit.template.review_approved', [
                'stage' => $stage,
                'name' => $this->templateName ?: trans('audit.unnamed', [], 'es'),
                'by_info' => $byInfo,
            ], 'es'),
            'template_name' => $this->templateName,
            'reviewer_name' => $this->reviewerName,
            'reviewer_stage' => $stage,
            'url' => "/templates/{$this->templateId}/review",
            'target_app' => 'dms',
        ], static fn ($v): bool => $v !== null && $v !== '');

        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'template',
            'entityId' => $this->templateId,
            'action' => 'review_approved',
            'userId' => $this->actorId,
            'previousValue' => [
                'stage' => $stage,
                'status' => 'pending',
            ],
            'newValue' => [
                'stage' => $stage,
                'status' => 'approved',
                'reviewer_name' => $this->reviewerName,
                '_context' => $context,
            ],
        ];
    }
}
