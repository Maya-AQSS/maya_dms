<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DocumentReview $resource
 */
class DocumentReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DocumentReview $review */
        $review = $this->resource;

        return [
            'id' => (string) $review->id,
            'document_id' => (string) $review->document_id,
            'reviewer_id' => (string) $review->reviewer_id,
            'stage' => (int) $review->stage,
            'status' => (string) $review->status,
            'rejection_reason' => $review->rejection_reason,
            'reviewed_at' => $review->reviewed_at?->toIso8601String(),
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }
}
