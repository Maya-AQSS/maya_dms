<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CommentRepository implements CommentRepositoryInterface
{
    /**
     * Indica si el usuario es autor del comentario o propietario/creador
     * del documento padre. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrDocumentOwner(string $commentId, string $userId): bool
    {
        $isAuthor = DB::table('comments')
            ->where('id', $commentId)
            ->where('author_id', $userId)
            ->exists();

        if ($isAuthor) {
            return true;
        }

        return DB::table('comments')
            ->join('documents', 'comments.document_id', '=', 'documents.id')
            ->where('comments.id', $commentId)
            ->where(fn ($q) => $q
                ->where('documents.owner_id', $userId)
                ->orWhere('documents.created_by', $userId)
            )
            ->exists();
    }
}
