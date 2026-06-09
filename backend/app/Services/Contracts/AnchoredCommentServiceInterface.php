<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\AnchoredComment\AnchoredCommentDto;

interface AnchoredCommentServiceInterface
{
    /**
     * List all anchored comments for a resource.
     *
     * @return array<int, AnchoredCommentDto>
     */
    public function listForResource(string $resourceType, string $resourceId): array;

    /**
     * Get a specific anchored comment if it belongs to the resource.
     */
    public function getForResource(string $resourceType, string $resourceId, string $anchorId): ?AnchoredCommentDto;

    /**
     * Create a new anchored comment on a resource.
     */
    public function createForResource(
        string $resourceType,
        string $resourceId,
        string $commentId,
        int $anchorFrom,
        int $anchorTo,
        ?string $anchorTextSnapshot,
    ): AnchoredCommentDto;

    /**
     * Update an anchored comment.
     */
    public function updateAnchor(
        string $anchorId,
        int $anchorFrom,
        int $anchorTo,
        ?string $anchorTextSnapshot,
    ): AnchoredCommentDto;

    /**
     * Delete an anchored comment.
     */
    public function deleteAnchor(string $anchorId): void;
}
