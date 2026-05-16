<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface UserDirectoryServiceInterface
{
    /**
     * Busca usuarios por nombre o email.
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array;

    /**
     * Usuarios con permiso `templates.review`.
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchTemplateReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array;

    /**
     * Usuarios con permiso `documents.review`.
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchDocumentReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array;
}
