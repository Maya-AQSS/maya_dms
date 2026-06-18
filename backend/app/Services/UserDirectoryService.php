<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Users\ReviewerCandidateFilterDto;
use App\DTOs\Users\UserSummaryDto;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\UserDirectoryServiceInterface;

/**
 * Orquesta búsquedas de directorio; candidatos a validador = filas en `user_resolved_permissions`
 * con el permiso de revisión pedido (sin filtro por ámbito académico de plantilla).
 */
class UserDirectoryService implements UserDirectoryServiceInterface
{
    public function __construct(
        private readonly UserDirectoryRepositoryInterface $repository,
    ) {}

    /**
     * Busca usuarios por nombre o email.
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->toSummaries(
            $this->repository->searchUsers($search, $limit, $excludeUserId),
        );
    }

    public function searchTemplateReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerCandidateFilterDto $academicFilter = null,
    ): array {
        unset($academicFilter);

        return $this->toSummaries(
            $this->repository->searchTemplateReviewerCandidates($search, $limit, $excludeUserId, null),
        );
    }

    public function searchDocumentReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerCandidateFilterDto $academicFilter = null,
    ): array {
        unset($academicFilter);

        return $this->toSummaries(
            $this->repository->searchDocumentReviewerCandidates($search, $limit, $excludeUserId, null),
        );
    }

    /**
     * Mapea las filas estructuradas del repositorio a una lista de DTOs.
     *
     * @param  list<array{id: mixed, name?: mixed, email?: mixed, role?: mixed}>  $rows
     * @return list<UserSummaryDto>
     */
    private function toSummaries(array $rows): array
    {
        return array_map(static fn (array $row): UserSummaryDto => UserSummaryDto::fromRow($row), $rows);
    }
}
