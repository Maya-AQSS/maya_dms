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
        public readonly ?string $documentTitle = null,
    ) {}

    public function toAuditPayload(): array
    {
        $stage = (int) $this->review->stage;
        $byInfo = $this->reviewerName
            ? trans('audit.by_reviewer', ['reviewer' => $this->reviewerName], 'es')
            : '';

        $context = array_filter([
            'description' => trans('audit.document.review_approved', [
                'stage' => $stage,
                'title' => $this->documentTitle ?: trans('audit.unnamed', [], 'es'),
                'by_info' => $byInfo,
            ], 'es'),
            'document_title' => $this->documentTitle,
            'reviewer_name' => $this->reviewerName,
            'reviewer_stage' => $stage,
        ], static fn ($v): bool => $v !== null && $v !== '');

        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'document',
            'entityId' => $this->documentId,
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
