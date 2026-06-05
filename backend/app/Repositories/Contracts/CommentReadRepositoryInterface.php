<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface CommentReadRepositoryInterface
{
    /**
     * Marca un comentario como leído para el usuario (idempotente).
     */
    public function markAsRead(string $commentId, string $userId): void;

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
