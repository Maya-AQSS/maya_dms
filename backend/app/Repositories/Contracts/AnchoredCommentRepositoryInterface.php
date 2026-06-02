<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AnchoredComment;
use Illuminate\Database\Eloquent\Collection;

interface AnchoredCommentRepositoryInterface
{
    /**
     * Find an anchored comment by ID or fail.
     */
    public function findByIdOrFail(string $id): AnchoredComment;

    /**
     * Find all anchored comments for a resource, ordered by anchor position.
     */
    public function findByResource(string $resourceType, string $resourceId): Collection;

    /**
     * Create a new anchored comment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AnchoredComment;

    /**
     * Update an anchored comment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(AnchoredComment $anchor, array $attributes): AnchoredComment;

    /**
     * Delete an anchored comment.
     */
    public function delete(AnchoredComment $anchor): void;
}
