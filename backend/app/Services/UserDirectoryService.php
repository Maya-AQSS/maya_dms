<?php

namespace App\Services;

use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\UserDirectoryServiceInterface;

class UserDirectoryService implements UserDirectoryServiceInterface
{
    public function __construct(
        private readonly UserDirectoryRepositoryInterface $repository,
    ) {}

    /**
     * Busca usuarios por nombre, email o departamento.
     * 
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->repository->searchUsers($search, $limit, $excludeUserId);
    }

    /**
     * Busca candidatos a revisor con permiso templates.review.
     * 
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchTemplateReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->repository->searchTemplateReviewerCandidates($search, $limit, $excludeUserId);
    }

    /**
     * Busca candidatos a revisor con permiso documents.review.
     * 
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchDocumentReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->repository->searchDocumentReviewerCandidates($search, $limit, $excludeUserId);
    }
}
