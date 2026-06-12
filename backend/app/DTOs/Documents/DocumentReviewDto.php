<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Http\Resources\DocumentReviewResource;
use App\Models\DocumentReview;

/**
 * Revisión de documento para exposición API ({@see DocumentReviewResource}).
 * Emitido por DocumentService::listReviews() — los Services no devuelven Models.
 */
final readonly class DocumentReviewDto
{
    public function __construct(
        public string $id,
        public string $documentId,
        public string $reviewerId,
        public ?string $reviewerName,
        public int $stage,
        public string $status,
        public ?string $rejectionReason,
        public ?string $reviewedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(DocumentReview $r): self
    {
        return new self(
            id: (string) $r->id,
            documentId: (string) $r->document_id,
            reviewerId: (string) $r->reviewer_id,
            reviewerName: $r->relationLoaded('reviewer') ? ($r->reviewer?->name ?? null) : null,
            stage: (int) $r->stage,
            status: (string) $r->status,
            rejectionReason: $r->rejection_reason !== null ? (string) $r->rejection_reason : null,
            reviewedAt: $r->reviewed_at?->toIso8601String(),
            createdAt: $r->created_at?->toIso8601String(),
            updatedAt: $r->updated_at?->toIso8601String(),
        );
    }
}
