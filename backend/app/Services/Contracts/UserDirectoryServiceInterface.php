<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Users\ReviewerCandidateFilterDto;
use App\DTOs\Users\UserSummaryDto;

interface UserDirectoryServiceInterface
{
    /**
     * Busca usuarios por nombre o email.
     *
     * @return list<UserSummaryDto>
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array;

    /**
     * Usuarios con permiso `templates.review`.
     *
     * @return list<UserSummaryDto>
     */
    public function searchTemplateReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerCandidateFilterDto $academicFilter = null,
    ): array;

    /**
     * Usuarios con permiso `documents.review`.
     *
     * @return list<UserSummaryDto>
     */
    public function searchDocumentReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerCandidateFilterDto $academicFilter = null,
    ): array;
}
