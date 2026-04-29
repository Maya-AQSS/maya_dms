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
}

