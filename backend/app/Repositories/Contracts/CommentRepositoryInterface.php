<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CommentRepositoryInterface
{
    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id, ?string $readerUserId = null): Comment;

    /**
     * Lista comentarios paginados por recurso comentable.
     */
    public function listForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        int $perPage,
        ?string $readerUserId = null,
    ): LengthAwarePaginator;

    /**
     * Comentarios de un bloque concretos (incluye soft-deleted), con estado de lectura del lector.
     *
     * @return Collection<int, Comment>
     */
    public function listForBlock(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        string $blockableType,
        string $blockableId,
        ?string $readerUserId = null,
    ): Collection;

    /**
     * Crea un comentario.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Comment;

    /**
     * Actualiza el cuerpo del comentario y guarda la versión anterior en comment_edits.
     */
    public function update(string $commentId, string $newBody, string $editedBy): Comment;

    /**
     * Marca un comentario como eliminado (soft delete) con metadatos de quién lo borró.
     */
    public function delete(string $commentId, string $deletedBy, string $deletedByName): void;

    /**
     * Busca un comentario por ID ignorando scopes globales.
     */
    public function findWithoutScopesById(string $id): ?Comment;

    /**
     * Indica si el usuario es autor del comentario o propietario/creador
     * del documento padre. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrDocumentOwner(string $commentId, string $userId): bool;

    /**
     * Indica si existe un bloque de plantilla para una plantilla.
     */
    public function existsTemplateBlockForTemplate(string $blockId, string $templateId): bool;

    /**
     * Indica si existe un bloque de documento para un documento.
     */
    public function existsDocumentBlockForDocument(string $blockId, string $documentId): bool;

    /**
     * Indica si existen comentarios visibles para el usuario autenticado
     * sobre un recurso comentable dado (type + id).
     *
     * Aplica el scope global `user_access` del modelo Comment, por lo que
     * solo devuelve true si el usuario autenticado puede ver al menos uno.
     */
    public function existsForCommentable(string $commentableType, string $commentableId): bool;

    /**
     * Indica si el autor indicado tiene al menos un comentario activo (no eliminado) sobre
     * el recurso comentable. Ignora los scopes globales del modelo para que funcione
     * también en contextos donde el usuario no es el autenticado actual.
     *
     * Semántica exacta: withoutGlobalScopes + author_id + whereNull('deleted_at').
     */
    public function authorHasActiveCommentOnCommentable(
        string $commentableType,
        string $commentableId,
        string $authorId,
    ): bool;
}
