<?php

namespace App\Services\Contracts;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommentServiceInterface
{
    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment;

    /**
     * Lista los comentarios paginados para un recurso.
     */
    public function listForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        int $perPage,
    ): LengthAwarePaginator;

    /**
     * Crea un comentario para un recurso.
     */
    public function createForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        ?string $blockableType,
        ?string $blockableId,
        ?string $parentId,
        string $authorId,
        string $body,
    ): Comment;

    /**
     * Elimina un comentario.
     */
    public function delete(Comment $comment): void;

    /**
     * Marca un comentario como resuelto.
     */
    public function resolve(Comment $comment, string $userId): Comment;
}
