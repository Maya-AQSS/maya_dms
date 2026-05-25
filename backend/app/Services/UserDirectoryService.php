<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Users\ReviewerCandidateFilterDto;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\UserDirectoryServiceInterface;

/**
 * Orquesta búsquedas de directorio; candidatos a validador = filas en `user_resolved_permissions` con el permiso de revisión pedido.
 */
class UserDirectoryService implements UserDirectoryServiceInterface
{
    public function __construct(
        private readonly UserDirectoryRepositoryInterface $repository,
        private readonly ReviewerAcademicScopeResolver $academicScopeResolver,
    ) {}

    /**
     * Busca usuarios por nombre o email.
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->repository->searchUsers($search, $limit, $excludeUserId);
    }

    public function searchTemplateReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerCandidateFilterDto $academicFilter = null,
    ): array {
        $scope = $academicFilter !== null
            ? $this->academicScopeResolver->resolveFromFilter($academicFilter)
            : null;

        return $this->repository->searchTemplateReviewerCandidates($search, $limit, $excludeUserId, $scope);
    }

    public function searchDocumentReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerCandidateFilterDto $academicFilter = null,
    ): array {
        $scope = $academicFilter !== null
            ? $this->academicScopeResolver->resolveFromFilter($academicFilter)
            : null;

        return $this->repository->searchDocumentReviewerCandidates($search, $limit, $excludeUserId, $scope);
    }
}
