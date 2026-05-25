<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Favoritos usuario ↔ plantilla / documento (tablas pivote).
 */
interface UserFavoriteRepositoryInterface
{
    /**
     * Lista de IDs de plantillas favoritas del usuario.
     *
     * @return list<string>
     */
    public function listTemplateIdsForUser(string $userId): array;

    /**
     * Lista de IDs de documentos favoritos del usuario.
     *
     * @return list<string>
     */
    public function listDocumentIdsForUser(string $userId): array;

    /**
     * Añade una versión de plantilla favorita al usuario.
     */
    public function addTemplateFavorite(string $userId, string $templateVersionId): void;

    /**
     * Elimina una versión de plantilla favorita del usuario.
     */
    public function removeTemplateFavorite(string $userId, string $templateVersionId): void;

    /**
     * Añade un documento favorito al usuario.
     */
    public function addDocumentFavorite(string $userId, string $documentId): void;

    /**
     * Elimina un documento favorito del usuario.
     */
    public function removeDocumentFavorite(string $userId, string $documentId): void;

    /**
     * Reasigna todos los favoritos que apuntaban a $oldVersionId para que apunten a $newVersionId.
     * Se llama dentro de la transacción de publicación para migrar favoritos automáticamente.
     */
    public function migrateFavoriteTemplateVersion(string $oldVersionId, string $newVersionId): void;
}
