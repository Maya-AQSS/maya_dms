<?php

namespace App\Services\Contracts;

use App\Models\Document;
use App\Models\Template;

/**
 * Favoritos de plantillas y documentos para el usuario autenticado.
 */
interface UserFavoriteServiceInterface
{
    /**
     * Resuelve un Template para el flujo de favoritos. La autorización vía Gate
     * se ejecuta sobre el Model resultante en el controlador.
     */
    public function findTemplateOrFail(string $templateId): Template;

    /**
     * Resuelve un Document para el flujo de favoritos. La autorización vía Gate
     * se ejecuta sobre el Model resultante en el controlador.
     */
    public function findDocumentOrFail(string $documentId): Document;

    /**
     * Lista de IDs de plantillas y documentos favoritos del usuario.
     *
     * @return array{template_ids: list<string>, document_ids: list<string>}
     */
    public function listIdsForUser(string $userId): array;

    /**
     * Añade una plantilla favorita al usuario.
     */
    public function addTemplateFavorite(string $userId, string $templateId): void;

    /**
     * Elimina una plantilla favorita del usuario.
     */
    public function removeTemplateFavorite(string $userId, string $templateId): void;

    /**
     * Añade un documento favorito al usuario.
     */
    public function addDocumentFavorite(string $userId, string $documentId): void;

    /**
     * Elimina un documento favorito del usuario.
     */
    public function removeDocumentFavorite(string $userId, string $documentId): void;
}
