<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface CommentReadRepositoryInterface
{
    /**
     * Marca un comentario como leído para el usuario (idempotente).
     */
    /**
     * @return bool true si el comentario quedó recién marcado; false si ya estaba leído.
     */
    public function markAsRead(string $commentId, string $userId): bool;

    /**
     * Marca como leídos todos los comentarios activos de un bloque para el usuario.
     *
     * @return int Número de filas insertadas (comentarios recién marcados).
     */
    public function markBlockAsRead(
        string $userId,
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        string $blockableType,
        string $blockableId,
    ): int;
}
