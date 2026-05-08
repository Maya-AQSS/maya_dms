<?php

namespace App\Services;

use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\UserDirectoryServiceInterface;

/**
 * Orquesta búsquedas de directorio; candidatos a validador = filas en `user_permissions` con el permiso de revisión pedido.
 */
class UserDirectoryService implements UserDirectoryServiceInterface
{
    public function __construct(
        private readonly UserDirectoryRepositoryInterface $repository,
    ) {}

    /**
     * Busca usuarios por nombre o email.
     *
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->repository->searchUsers($search, $limit, $excludeUserId);
    }

    public function searchTemplateReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->repository->searchTemplateReviewerCandidates($search, $limit, $excludeUserId);
    }

    public function searchDocumentReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->repository->searchDocumentReviewerCandidates($search, $limit, $excludeUserId);
    }
}
