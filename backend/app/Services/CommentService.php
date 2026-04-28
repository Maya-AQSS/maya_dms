<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Template;
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

    public function listForResource(string $commentableType, string $commentableId): Collection
    {
        return Comment::query()
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->with('author:id,name')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function createForResource(
        string $commentableType,
        string $commentableId,
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

        $this->assertBlockBelongsToResource($commentableType, $commentableId, $blockableType, $blockableId);

        return Comment::create([
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
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
}
