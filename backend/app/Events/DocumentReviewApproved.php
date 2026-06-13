<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DocumentReview;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Hecho de negocio: un revisor asignado aprueba su etapa.
 */
class DocumentReviewApproved implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $documentId,
        public readonly DocumentReview $review,
        public readonly string $actorId,
        public readonly ?string $reviewerName = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'document',
            'entityId' => $this->documentId,
            'action' => 'review_approved',
            'userId' => $this->actorId,
            'previousValue' => [
                'stage' => (int) $this->review->stage,
                'status' => 'pending',
            ],
            'newValue' => [
                'stage' => (int) $this->review->stage,
                'status' => 'approved',
                'reviewer_name' => $this->reviewerName,
            ],
        ];
    }
}
