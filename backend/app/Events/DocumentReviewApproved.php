<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DocumentReview;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

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
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => 'document',
            'entityId' => $this->documentId,
            'action' => 'review_approved',
            'userId' => $this->actorId,
            'previousValue' => [
                'review_id' => (string) $this->review->id,
                'stage' => (int) $this->review->stage,
                'status' => 'pending',
            ],
            'newValue' => [
                'review_id' => (string) $this->review->id,
                'stage' => (int) $this->review->stage,
                'status' => 'approved',
            ],
        ];
    }
}
