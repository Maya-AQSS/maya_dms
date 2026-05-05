<?php

namespace App\Services;

use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use App\Services\Contracts\UserFavoriteServiceInterface;

class UserFavoriteService implements UserFavoriteServiceInterface
{
    public function __construct(
        private readonly UserFavoriteRepositoryInterface $repository,
    ) {}

    /**
     * Lista de IDs de plantillas y documentos favoritos del usuario.
     * 
     * @return array{template_ids: list<string>, document_ids: list<string>}
     */
    public function listIdsForUser(string $userId): array
    {
        return [
            'template_ids' => $this->repository->listTemplateIdsForUser($userId),
            'document_ids' => $this->repository->listDocumentIdsForUser($userId),
        ];
    }

    /**
     * Añade una plantilla favorita al usuario.
     */
    public function addTemplateFavorite(string $userId, string $templateId): void
    {
        $this->repository->addTemplateFavorite($userId, $templateId);
    }

    /**
     * Elimina una plantilla favorita del usuario.
     */
    public function removeTemplateFavorite(string $userId, string $templateId): void
    {
        $this->repository->removeTemplateFavorite($userId, $templateId);
    }

    /**
     * Añade un documento favorito al usuario.
     */
    public function addDocumentFavorite(string $userId, string $documentId): void
    {
        $this->repository->addDocumentFavorite($userId, $documentId);
    }

    /**
     * Elimina un documento favorito del usuario.
     */
    public function removeDocumentFavorite(string $userId, string $documentId): void
    {
        $this->repository->removeDocumentFavorite($userId, $documentId);
    }
}
