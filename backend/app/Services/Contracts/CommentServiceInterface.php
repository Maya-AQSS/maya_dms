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
     * Lista comentarios de una plantilla.
     */
    public function listForTemplate(string $templateId): \Illuminate\Support\Collection;

    /**
     * Crea un comentario para una plantilla.
     */
    public function createForTemplate(string $templateId, string $authorId, array $data): Comment;

    /**
     * Marca un comentario como resuelto.
     */
    public function resolve(string $id, string $userId): Comment;
}
