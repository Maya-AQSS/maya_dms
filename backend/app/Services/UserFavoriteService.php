<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use App\Services\Contracts\UserFavoriteServiceInterface;

class UserFavoriteService implements UserFavoriteServiceInterface
{
    public function __construct(
        private readonly UserFavoriteRepositoryInterface $repository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function findTemplateModelOrFail(string $templateId): Template
    {
        return $this->templateRepository->findOrFail($templateId);
    }

    public function findDocumentModelOrFail(string $documentId): Document
    {
        return $this->documentRepository->findOrFail($documentId);
    }

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

    public function addTemplateFavorite(string $userId, string $templateId): void
    {
        $this->repository->addTemplateFavorite($userId, $templateId);
    }

    public function removeTemplateFavorite(string $userId, string $templateId): void
    {
        $this->repository->removeTemplateFavorite($userId, $templateId);
    }

    public function addDocumentFavorite(string $userId, string $documentId): void
    {
        $this->repository->addDocumentFavorite($userId, $documentId);
    }

    public function removeDocumentFavorite(string $userId, string $documentId): void
    {
        $this->repository->removeDocumentFavorite($userId, $documentId);
    }
}
