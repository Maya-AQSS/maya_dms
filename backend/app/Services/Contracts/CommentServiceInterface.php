<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Comments\CommentDto;
use App\Models\Comment;
use Maya\Http\Pagination\PaginatedDto;

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
     * Edita el cuerpo de un comentario y registra la versión anterior en el historial.
     */
    public function update(string $commentId, string $body, string $editedBy): CommentDto;

    /**
     * Elimina un comentario por id (la autorización se resuelve en el Controller
     * antes de delegar; el Service trabaja con el id, no con el Model).
     */
    public function delete(string $commentId, string $deletedBy, string $deletedByName): void;
}
