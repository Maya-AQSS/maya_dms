<?php

namespace App\Events;

use App\Models\DocumentReview;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Hecho de negocio: un revisor asignado rechaza su etapa con motivo opcional.
 */
class DocumentReviewRejected implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $documentId,
        public readonly DocumentReview $review,
        public readonly string $actorId,
        public readonly ?string $reason = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType'      => 'document',
            'entityId'        => $this->documentId,
            'action'          => 'review_rejected',
            'userId'          => $this->actorId,
            'previousValue'   => [
                'review_id' => (string) $this->review->id,
                'stage'     => (int) $this->review->stage,
                'status'    => 'pending',
            ],
            'newValue'        => [
                'review_id'        => (string) $this->review->id,
                'stage'            => (int) $this->review->stage,
                'status'           => 'rejected',
                'rejection_reason' => $this->reason,
            ],
        ];
    }
}
