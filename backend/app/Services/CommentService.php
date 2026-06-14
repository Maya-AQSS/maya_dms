<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Comments\CommentDto;
use App\Events\BlockCommentCreated;
use App\Events\BlockCommentDeleted;
use App\Events\BlockCommentMarkedRead;
use App\Events\BlockCommentsMarkedRead;
use App\Events\BlockCommentUpdated;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\CommentReadRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Validation\ValidationException;
use Maya\Http\Pagination\PaginatedDto;

class CommentService implements CommentServiceInterface
{
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly CommentReadRepositoryInterface $commentReadRepository,
    ) {}

    /**
     * @internal Used by controllers for policy gates only — do not use in service-to-service calls.
     */
    public function findModelOrFail(string $id, ?string $readerUserId = null): Comment
    {
        return $this->commentRepository->findOrFail($id, $readerUserId);
    }

    public function findOrFail(string $id, ?string $readerUserId = null): CommentDto
    {
        return CommentDto::fromModel($this->commentRepository->findOrFail($id, $readerUserId));
    }

    /**
     * @return PaginatedDto<CommentDto>
     */
    public function listForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        int $perPage,
        ?string $readerUserId = null,
    ): PaginatedDto {
        $page = $this->commentRepository->listForResource(
            $commentableType,
            $commentableId,
            $commentableVersion,
            $perPage,
            $readerUserId,
        );

        return PaginatedDto::fromPaginator(
            $page,
            static fn (Comment $comment): CommentDto => CommentDto::fromModel($comment),
        );
    }

    public function createForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        ?string $blockableType,
        ?string $blockableId,
        ?string $parentId,
        string $authorId,
        string $body,
    ): CommentDto {
        if (! in_array($commentableType, Comment::ALLOWED_COMMENTABLE_TYPES, true)) {
            throw ValidationException::withMessages([
                'commentable_type' => [__('validation.comment.resource_not_allowed')],
            ]);
        }

        if (($blockableType === null) !== ($blockableId === null)) {
            throw ValidationException::withMessages([
                'blockable' => [__('validation.comment.block_type_id')],
            ]);
        }

        $this->assertParentBelongsToResource(
            parentId: $parentId,
            commentableType: $commentableType,
            commentableId: $commentableId,
            commentableVersion: $commentableVersion,
            blockableType: $blockableType,
            blockableId: $blockableId,
        );

        $this->assertBlockBelongsToResource($commentableType, $commentableId, $blockableType, $blockableId);

        $comment = $this->commentRepository->create([
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'commentable_version' => $commentableVersion,
            'blockable_type' => $blockableType,
            'blockable_id' => $blockableId,
            'parent_id' => $parentId,
            'author_id' => $authorId,
            'body' => $body,
        ]);

        BlockCommentCreated::dispatch($comment, $authorId);

        return $this->findOrFail((string) $comment->id, $authorId);
    }

    public function update(string $commentId, string $body, string $editedBy, ?string $readerUserId = null): CommentDto
    {
        $before = $this->commentRepository->findOrFail($commentId);
        $previousBody = (string) $before->body;

        $comment = $this->commentRepository->update($commentId, $body, $editedBy);

        BlockCommentUpdated::dispatch($comment, $editedBy, $previousBody);

        return $this->findOrFail($commentId, $readerUserId);
    }

    public function delete(string $commentId, string $deletedBy, string $deletedByName): void
    {
        $comment = $this->commentRepository->findOrFail($commentId);

        $this->commentRepository->delete($commentId, $deletedBy, $deletedByName);

        BlockCommentDeleted::dispatch($comment, $deletedBy);
    }

    public function markAsRead(string $commentId, string $userId): CommentDto
    {
        $comment = $this->commentRepository->findOrFail($commentId, $userId);

        if ($this->commentReadRepository->markAsRead($commentId, $userId)) {
            BlockCommentMarkedRead::dispatch($comment, $userId);
        }

        return $this->findOrFail($commentId, $userId);
    }

    public function markBlockAsRead(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        string $blockableType,
        string $blockableId,
        string $userId,
    ): int {
        return $this->commentReadRepository->markBlockAsRead(
            $userId,
            $commentableType,
            $commentableId,
            $commentableVersion,
            $blockableType,
            $blockableId,
        );
    }

    /**
     * @return list<CommentDto>
     */
    public function markBlockCommentsAsRead(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        string $blockableType,
        string $blockableId,
        string $userId,
    ): array {
        $this->assertBlockBelongsToResource(
            $commentableType,
            $commentableId,
            $blockableType,
            $blockableId,
        );

        $markedCount = $this->commentReadRepository->markBlockAsRead(
            $userId,
            $commentableType,
            $commentableId,
            $commentableVersion,
            $blockableType,
            $blockableId,
        );

        if ($markedCount > 0) {
            BlockCommentsMarkedRead::dispatch(
                commentableType: $commentableType,
                commentableId: $commentableId,
                commentableVersion: $commentableVersion,
                blockableType: $blockableType,
                blockableId: $blockableId,
                readerUserId: $userId,
                markedCount: $markedCount,
            );
        }

        return $this->commentRepository
            ->listForBlock(
                $commentableType,
                $commentableId,
                $commentableVersion,
                $blockableType,
                $blockableId,
                $userId,
            )
            ->map(static fn (Comment $comment): CommentDto => CommentDto::fromModel($comment))
            ->all();
    }

    private function assertBlockBelongsToResource(
        string $commentableType,
        string $commentableId,
        ?string $blockableType,
        ?string $blockableId,
    ): void {
        if ($blockableType === null || $blockableId === null) {
            return;
        }

        if ($commentableType === Template::class) {
            if ($blockableType !== TemplateBlock::class) {
                throw ValidationException::withMessages([
                    'blockable_type' => [__('validation.comment.block_template')],
                ]);
            }

            $exists = $this->commentRepository->existsTemplateBlockForTemplate($blockableId, $commentableId);

            if (! $exists) {
                throw ValidationException::withMessages([
                    'blockable_id' => [__('validation.comment.block_not_template')],
                ]);
            }

            return;
        }

        if ($commentableType === Document::class) {
            if ($blockableType !== DocumentBlock::class) {
                throw ValidationException::withMessages([
                    'blockable_type' => [__('validation.comment.block_document')],
                ]);
            }

            $exists = $this->commentRepository->existsDocumentBlockForDocument($blockableId, $commentableId);

            if (! $exists) {
                throw ValidationException::withMessages([
                    'blockable_id' => [__('validation.comment.block_not_document')],
                ]);
            }
        }
    }

    private function assertParentBelongsToResource(
        ?string $parentId,
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        ?string $blockableType,
        ?string $blockableId,
    ): void {
        if ($parentId === null) {
            return;
        }

        $parent = $this->commentRepository->findWithoutScopesById($parentId);
        if (! $parent instanceof Comment) {
            throw ValidationException::withMessages([
                'parent_id' => [__('validation.comment.parent_not_found')],
            ]);
        }

        if ($parent->deleted_at !== null) {
            throw ValidationException::withMessages([
                'parent_id' => [__('validation.comment.parent_unavailable')],
            ]);
        }

        if (
            (string) $parent->commentable_type !== $commentableType
            || (string) $parent->commentable_id !== $commentableId
            || (int) $parent->commentable_version !== $commentableVersion
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [__('validation.comment.parent_same_resource')],
            ]);
        }

        if (
            (string) ($parent->blockable_type ?? '') !== (string) ($blockableType ?? '')
            || (string) ($parent->blockable_id ?? '') !== (string) ($blockableId ?? '')
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [__('validation.comment.parent_same_block')],
            ]);
        }
    }
}
