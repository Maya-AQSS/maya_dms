<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\AnchoredComment\AnchoredCommentDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AnchoredCommentDto $resource
 */
class AnchoredCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AnchoredCommentDto $dto */
        $dto = $this->resource;

        return [
            'id' => $dto->id,
            'comment_id' => $dto->commentId,
            'resource_type' => $dto->resourceType,
            'resource_id' => $dto->resourceId,
            'anchor_from' => $dto->anchorFrom,
            'anchor_to' => $dto->anchorTo,
            'anchor_text_snapshot' => $dto->anchorTextSnapshot,
            'anchor_is_valid' => $dto->anchorIsValid,
            'anchor_last_synced_at' => $dto->anchorLastSyncedAt,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
            'comment' => $dto->source->comment,
        ];
    }
}
