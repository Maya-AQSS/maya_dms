<?php

namespace App\Http\Resources;

use App\DTOs\Comments\CommentDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dto = $this->resource instanceof CommentDto
            ? $this->resource
            : CommentDto::fromModel($this->resource);

        $payload = [
            'id' => $dto->id,
            'commentable_type' => $dto->commentableType,
            'commentable_id' => $dto->commentableId,
            'commentable_version' => $dto->commentableVersion,
            'blockable_type' => $dto->blockableType,
            'blockable_id' => $dto->blockableId,
            'parent_id' => $dto->parentId,
            'author_id' => $dto->authorId,
            'body' => $dto->body,
            'resolved' => $dto->resolved,
            'resolved_by' => $dto->resolvedBy,
            'resolved_at' => $dto->resolvedAt,
            'created_at' => $dto->createdAt,
        ];

        if ($dto->authorLoaded) {
            $payload['author'] = $dto->author !== null
                ? ['id' => $dto->author->id, 'name' => $dto->author->name]
                : null;
        }

        return $payload;
    }
}
