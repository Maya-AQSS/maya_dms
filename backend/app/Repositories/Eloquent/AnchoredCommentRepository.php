<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AnchoredComment;
use App\Repositories\Contracts\AnchoredCommentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AnchoredCommentRepository implements AnchoredCommentRepositoryInterface
{
    public function findByIdOrFail(string $id): AnchoredComment
    {
        return AnchoredComment::findOrFail($id);
    }

    public function findByResource(string $resourceType, string $resourceId): Collection
    {
        return AnchoredComment::query()
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->with('comment')
            ->orderBy('anchor_from')
            ->get();
    }

    public function create(array $attributes): AnchoredComment
    {
        return AnchoredComment::create($attributes);
    }

    public function update(AnchoredComment $anchor, array $attributes): AnchoredComment
    {
        $anchor->update($attributes);

        return $anchor;
    }

    public function delete(AnchoredComment $anchor): void
    {
        $anchor->delete();
    }
}
