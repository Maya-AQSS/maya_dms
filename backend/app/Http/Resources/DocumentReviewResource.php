<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\DocumentReviewDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DocumentReviewDto $resource
 */
class DocumentReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DocumentReviewDto $dto */
        $dto = $this->resource;

        return [
            'id' => $dto->id,
            'document_id' => $dto->documentId,
            'reviewer_id' => $dto->reviewerId,
            'reviewer_name' => $dto->reviewerName,
            'stage' => $dto->stage,
            'status' => $dto->status,
            'rejection_reason' => $dto->rejectionReason,
            'reviewed_at' => $dto->reviewedAt,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}
