<?php

namespace App\Services\Contracts;

use App\Models\Comment;

interface CommentServiceInterface
{
    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment;
}
