<?php

namespace App\Repositories\Contracts;

interface CommentRepositoryInterface
{
    /**
     * Indica si el usuario es autor del comentario o propietario/creador
     * del documento padre. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrDocumentOwner(string $commentId, string $userId): bool;
}
