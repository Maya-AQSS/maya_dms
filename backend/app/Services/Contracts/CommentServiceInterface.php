<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Comments\CommentDto;
use Maya\Http\Pagination\PaginatedDto;

interface CommentServiceInterface
{
    /**
     * Devuelve el DTO de un comentario. Lanza ModelNotFoundException si no existe.
     */
    public function findOrFail(string $id, ?string $readerUserId = null): CommentDto;

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
        ?string $readerUserId = null,
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
    public function update(string $commentId, string $body, string $editedBy, ?string $readerUserId = null): CommentDto;

    /**
     * Elimina un comentario por id. La autorización se resuelve en el Controller
     * (`findOrFail()` + policy sobre el DTO) antes de delegar; el Service trabaja
     * solo con el id.
     */
    public function delete(string $commentId, string $deletedBy, string $deletedByName): void;

    /**
     * Marca un comentario como leído para el usuario indicado.
     */
    public function markAsRead(string $commentId, string $userId): CommentDto;

    /**
     * Marca como leídos todos los comentarios de un bloque para el usuario.
     *
     * @return int Número de comentarios recién marcados como leídos.
     */
    public function markBlockAsRead(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        string $blockableType,
        string $blockableId,
        string $userId,
    ): int;

    /**
     * Marca como leídos los comentarios de un bloque y devuelve el listado actualizado del bloque.
     *
     * @return list<CommentDto>
     */
    public function markBlockCommentsAsRead(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        string $blockableType,
        string $blockableId,
        string $userId,
    ): array;
}
