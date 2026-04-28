<?php

namespace App\Services\Contracts;

use App\Models\Comment;

interface CommentServiceInterface
{
    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment;

    /**
     * Lista los comentarios para un recurso.
     */
    public function listForResource(string $commentableType, string $commentableId): \Illuminate\Support\Collection;

    /**
     * Crea un comentario para un recurso.
     */
    public function createForResource(
        string $commentableType,
        string $commentableId,
        ?string $blockableType,
        ?string $blockableId,
        ?string $parentId,
        string $authorId,
        string $body,
    ): Comment;

    /**
     * Elimina un comentario.
     */
    public function delete(string $id): void;

    /**
     * Marca un comentario como resuelto.
     */
    public function resolve(string $id, string $userId): Comment;
}
