<?php

namespace App\Services\Contracts;

use App\DTOs\Comments\CommentDto;
use App\DTOs\Pagination\PaginatedDto;
use App\Models\Comment;

interface CommentServiceInterface
{
    /**
     * Devuelve el DTO de un comentario. Lanza ModelNotFoundException si no existe.
     */
    public function findOrFail(string $id): CommentDto;

    /**
     * Devuelve el modelo Eloquent del comentario. Variante de uso interno
     * cuando el caller necesita el Model para la policy (`authorize('delete', $model)`).
     * Resto de consumidores deben usar `findOrFail()`.
     */
    public function findModelOrFail(string $id): Comment;

    /**
     * Lista los comentarios paginados para un recurso.
     *
     * @return PaginatedDto<CommentDto>
     */
    public function listForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        int $perPage,
    ): PaginatedDto;

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
    ): CommentDto;

    /**
     * Elimina un comentario. Recibe el Model Eloquent (las policies del Controller
     * ya lo cargan vía `findModelOrFail()` o el route binding).
     */
    public function delete(Comment $comment): void;
}
