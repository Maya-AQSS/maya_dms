<?php

namespace App\Services\Contracts;

use App\Models\Document;
use App\Models\Template;

/**
 * Favoritos de plantillas y documentos para el usuario autenticado.
 *
 * Excepción B4 documentada: {@see findTemplateModelOrFail()} y
 * {@see findDocumentModelOrFail()} devuelven el Model porque su único uso
 * en {@see \App\Http\Controllers\Api\FavoriteController} es
 * `Gate::authorize('view', $model)`. No se entrega como respuesta — no hay
 * conversión a DTO/Resource. Si en el futuro se devuelve detalle, añadir
 * `findTemplateOrFail(): TemplateDto` y `findDocumentOrFail(): DocumentDto`.
 */
interface UserFavoriteServiceInterface
{
    /**
     * Resuelve un Template para el flujo de favoritos. La autorización vía Gate
     * se ejecuta sobre el Model resultante en el controlador.
     */
    public function findTemplateModelOrFail(string $templateId): Template;

    /**
     * Resuelve un Document para el flujo de favoritos. La autorización vía Gate
     * se ejecuta sobre el Model resultante en el controlador.
     */
    public function findDocumentModelOrFail(string $documentId): Document;

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
