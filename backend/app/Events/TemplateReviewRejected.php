<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TemplateReviewer;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Hecho de negocio: un revisor asignado rechaza su etapa de la plantilla.
 */
class TemplateReviewRejected implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $templateId,
        public readonly TemplateReviewer $reviewer,
        public readonly string $actorId,
        public readonly ?string $reviewerName = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => 'template',
            'entityId' => $this->templateId,
            'action' => 'review_rejected',
            'userId' => $this->actorId,
            'previousValue' => [
                'stage' => (int) $this->reviewer->stage,
                'status' => 'pending',
            ],
            'newValue' => [
                'stage' => (int) $this->reviewer->stage,
                'status' => 'rejected',
                'reviewer_name' => $this->reviewerName,
            ],
        ];
    }
}
