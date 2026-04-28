<?php

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Template;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CommentRepository implements CommentRepositoryInterface
{
    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment
    {
        return Comment::findOrFail($id);
    }

    /**
     * Lista comentarios por recurso comentable.
     */
    public function listForResource(string $commentableType, string $commentableId): \Illuminate\Support\Collection
    {
        return Comment::query()
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->with('author:id,name')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Crea un comentario.
     */
    public function create(array $attributes): Comment
    {
        return Comment::create($attributes);
    }

    /**
     * Busca un comentario por ID ignorando scopes globales.
     */
    public function findWithoutScopesById(string $id): ?Comment
    {
        return Comment::withoutGlobalScopes()->find($id);
    }

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
            ->leftJoin('documents', function ($join) {
                $join->on('comments.commentable_id', '=', 'documents.id')
                    ->where('comments.commentable_type', '=', Document::class);
            })
            ->leftJoin('templates', function ($join) {
                $join->on('comments.commentable_id', '=', 'templates.id')
                    ->where('comments.commentable_type', '=', Template::class);
            })
            ->where('comments.id', $commentId)
            ->where(fn ($q) => $q
                ->where('documents.owner_id', $userId)
                ->orWhere('documents.created_by', $userId)
                ->orWhere('templates.created_by', $userId)
            )
            ->exists();
    }

    /**
     * Indica si existe un bloque de plantilla para una plantilla.
     */
    public function existsTemplateBlockForTemplate(string $blockId, string $templateId): bool
    {
        return DB::table('template_blocks')
            ->where('id', $blockId)
            ->where('template_id', $templateId)
            ->exists();
    }

    /**
     * Indica si existe un bloque de documento para un documento.
     */
    public function existsDocumentBlockForDocument(string $blockId, string $documentId): bool
    {
        return DB::table('document_blocks')
            ->where('id', $blockId)
            ->where('document_id', $documentId)
            ->exists();
    }
}
