<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AnchoredComment\AnchoredCommentDto;
use App\Repositories\Contracts\AnchoredCommentRepositoryInterface;
use App\Services\Contracts\AnchoredCommentServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class AnchoredCommentService implements AnchoredCommentServiceInterface
{
    public function __construct(
        private readonly AnchoredCommentRepositoryInterface $anchoredCommentRepository,
    ) {}

    public function listForResource(string $resourceType, string $resourceId): Collection
    {
        $anchors = $this->anchoredCommentRepository->findByResource($resourceType, $resourceId);

        return $anchors->map(static fn ($anchor) => AnchoredCommentDto::fromModel($anchor));
    }

    public function getForResource(string $resourceType, string $resourceId, string $anchorId): ?AnchoredCommentDto
    {
        try {
            $anchor = $this->anchoredCommentRepository->findByIdOrFail($anchorId);

            if ((string) $anchor->resource_type !== $resourceType || (string) $anchor->resource_id !== $resourceId) {
                return null;
            }

            return AnchoredCommentDto::fromModel($anchor);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return null;
        }
    }

    public function createForResource(
        string $resourceType,
        string $resourceId,
        string $commentId,
        int $anchorFrom,
        int $anchorTo,
        ?string $anchorTextSnapshot,
    ): AnchoredCommentDto {
        $anchor = $this->anchoredCommentRepository->create([
            'comment_id' => $commentId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'anchor_from' => $anchorFrom,
            'anchor_to' => $anchorTo,
            'anchor_text_snapshot' => $anchorTextSnapshot,
            'anchor_is_valid' => $anchorTo > $anchorFrom,
            'anchor_last_synced_at' => now(),
        ]);

        return AnchoredCommentDto::fromModel($anchor);
    }

    public function updateAnchor(
        string $anchorId,
        int $anchorFrom,
        int $anchorTo,
        ?string $anchorTextSnapshot,
    ): AnchoredCommentDto {
        $anchor = $this->anchoredCommentRepository->findByIdOrFail($anchorId);

        $anchor = $this->anchoredCommentRepository->update($anchor, [
            'anchor_from' => $anchorFrom,
            'anchor_to' => $anchorTo,
            'anchor_text_snapshot' => $anchorTextSnapshot ?? $anchor->anchor_text_snapshot,
            'anchor_is_valid' => $anchorTo > $anchorFrom,
            'anchor_last_synced_at' => now(),
        ]);

        return AnchoredCommentDto::fromModel($anchor);
    }

    public function deleteAnchor(string $anchorId): void
    {
        $anchor = $this->anchoredCommentRepository->findByIdOrFail($anchorId);
        $this->anchoredCommentRepository->delete($anchor);
    }
}
