<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommentRepositoryInterface
{
    /**
     * Localiza un comentario por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Comment;

    /**
     * Lista comentarios paginados por recurso comentable.
     */
    public function listForResource(
        string $commentableType,
        string $commentableId,
        int $commentableVersion,
        int $perPage,
    ): LengthAwarePaginator;

    /**
     * Crea un comentario.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Comment;

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
}
