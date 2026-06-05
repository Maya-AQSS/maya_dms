<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Models\CommentEdit;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Support\DocumentHeadSnapshot;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CommentRepository implements CommentRepositoryInterface
{
    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment
    {
        return Comment::query()->withCount('edits')->findOrFail($id);
    }

    /**
     * Lista comentarios paginados por recurso comentable.
     */
    public function listForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        int $perPage,
    ): LengthAwarePaginator {
        return Comment::withTrashed()
            ->select('comments.*')
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->where('commentable_version', $commentableVersion)
            ->with('author:id,name')
            ->withCount('edits')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Crea un comentario.
     */
    public function create(array $attributes): Comment
    {
        return Comment::create($attributes);
    }

    /**
     * Actualiza el cuerpo del comentario y guarda la versión anterior en comment_edits.
     */
    public function update(string $commentId, string $newBody, string $editedBy): Comment
    {
        $comment = $this->findOrFail($commentId);

        CommentEdit::create([
            'comment_id' => $comment->id,
            'previous_body' => $comment->body,
            'edited_by' => $editedBy,
            'edited_at' => now(),
        ]);

        $comment->update(['body' => $newBody]);

        return $comment->loadCount('edits');
    }

    /**
     * Busca un comentario por ID ignorando scopes globales.
     */
    public function findWithoutScopesById(string $id): ?Comment
    {
        return Comment::withoutGlobalScopes()->find($id);
    }

    /**
     * Marca un comentario como eliminado.
     */
    public function delete(string $commentId, string $deletedBy, string $deletedByName): void
    {
        $comment = $this->findOrFail($commentId);
        $comment->deleted_by = $deletedBy;
        $comment->deleted_by_name = $deletedByName;
        $comment->save();
        $comment->delete();
    }

    /**
     * Indica si el usuario es autor del comentario o propietario/creador
     * del documento padre. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrDocumentOwner(string $commentId, string $userId): bool
    {
        $isAuthor = Comment::query()
            ->where('id', $commentId)
            ->where('author_id', $userId)
            ->exists();

        if ($isAuthor) {
            return true;
        }

        return Comment::query()
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
                ->whereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id').' = ?', [$userId])
                ->orWhereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'created_by').' = ?', [$userId])
                ->orWhere(function ($q2) use ($userId) {
                    $q2->where('comments.commentable_type', Template::class)
                        ->whereExists(function ($sub) use ($userId) {
                            $sub->select(DB::raw(1))
                                ->from('entity_versions')
                                ->join('templates', 'templates.head_entity_version_id', '=', 'entity_versions.id')
                                ->whereColumn('templates.id', 'comments.commentable_id')
                                ->whereRaw(
                                    TemplateHeadSnapshot::jsonTemplateFieldExpression('entity_versions', 'created_by').' = ?',
                                    [$userId]
                                );
                        });
                })
            )
            ->exists();
    }

    /**
     * Indica si existe un bloque de plantilla para una plantilla.
     */
    public function existsTemplateBlockForTemplate(string $blockId, string $templateId): bool
    {
        return TemplateBlock::query()
            ->where('id', $blockId)
            ->where('template_id', $templateId)
            ->exists();
    }

    /**
     * Indica si existe un bloque de documento para un documento.
     */
    public function existsDocumentBlockForDocument(string $blockId, string $documentId): bool
    {
        return DocumentBlock::query()
            ->where('id', $blockId)
            ->where('document_id', $documentId)
            ->exists();
    }
}
