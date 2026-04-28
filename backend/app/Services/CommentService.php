<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CommentService implements CommentServiceInterface
{
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
    ) {}

    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment
    {
        return $this->commentRepository->findOrFail($id);
    }

    public function listForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
    ): Collection
    {
        return $this->commentRepository->listForResource($commentableType, $commentableId, $commentableVersion);
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
    ): Comment
    {
        if (! in_array($commentableType, Comment::ALLOWED_COMMENTABLE_TYPES, true)) {
            throw ValidationException::withMessages([
                'commentable_type' => ['Tipo de recurso no permitido para comentarios.'],
            ]);
        }

        if (($blockableType === null) !== ($blockableId === null)) {
            throw ValidationException::withMessages([
                'blockable' => ['El bloque debe incluir tipo e identificador juntos.'],
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

        return $this->commentRepository->create([
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'commentable_version' => $commentableVersion,
            'blockable_type' => $blockableType,
            'blockable_id' => $blockableId,
            'parent_id' => $parentId,
            'author_id' => $authorId,
            'body' => $body,
        ]);
    }

    public function delete(string $id): void
    {
        $this->findOrFail($id)->delete();
    }

    public function resolve(string $id, string $userId): Comment
    {
        $comment = $this->findOrFail($id);
        $comment->update([
            'resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
        return $comment;
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
                    'blockable_type' => ['El bloque debe ser de tipo plantilla.'],
                ]);
            }

            $exists = $this->commentRepository->existsTemplateBlockForTemplate($blockableId, $commentableId);

            if (! $exists) {
                throw ValidationException::withMessages([
                    'blockable_id' => ['El bloque no pertenece a la plantilla indicada.'],
                ]);
            }

            return;
        }

        if ($commentableType === Document::class) {
            if ($blockableType !== DocumentBlock::class) {
                throw ValidationException::withMessages([
                    'blockable_type' => ['El bloque debe ser de tipo documento.'],
                ]);
            }

            $exists = $this->commentRepository->existsDocumentBlockForDocument($blockableId, $commentableId);

            if (! $exists) {
                throw ValidationException::withMessages([
                    'blockable_id' => ['El bloque no pertenece al documento indicado.'],
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
                'parent_id' => ['El comentario padre no existe.'],
            ]);
        }

        if ($parent->deleted_at !== null) {
            throw ValidationException::withMessages([
                'parent_id' => ['El comentario padre no está disponible.'],
            ]);
        }

        if (
            (string) $parent->commentable_type !== $commentableType
            || (string) $parent->commentable_id !== $commentableId
            || (int) $parent->commentable_version !== $commentableVersion
        ) {
            throw ValidationException::withMessages([
                'parent_id' => ['El comentario padre debe pertenecer al mismo recurso y versión.'],
            ]);
        }

        if (
            (string) ($parent->blockable_type ?? '') !== (string) ($blockableType ?? '')
            || (string) ($parent->blockable_id ?? '') !== (string) ($blockableId ?? '')
        ) {
            throw ValidationException::withMessages([
                'parent_id' => ['El comentario padre debe pertenecer al mismo bloque.'],
            ]);
        }
    }
}
