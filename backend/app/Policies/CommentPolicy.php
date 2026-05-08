<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\JwtUser;

class CommentPolicy
{
    /**
     * Eliminación de comentario.
     *
     * Regla actual: solo el autor puede eliminar su comentario.
     */
    public function delete(JwtUser $user, Comment $comment): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $comment->author_id;
    }

    /**
     * Resolución de comentario.
     *
     * Quién puede marcar un comentario como resuelto:
     *  - El propio autor del comentario.
     *  - El propietario del recurso padre (plantilla / documento) —
     *    es el creador quien debe abordar el feedback del validador.
     */
    public function resolve(JwtUser $user, Comment $comment): bool
    {
        $userId = (string) $user->getAuthIdentifier();

        if ($userId === (string) $comment->author_id) {
            return true;
        }

        $commentable = $comment->commentable;

        return $commentable !== null
            && $userId === (string) ($commentable->created_by ?? '');
    }
}

