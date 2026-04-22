<?php

namespace App\Services\Contracts;

interface UserDirectoryServiceInterface
{
    /**
     * Busca usuarios por nombre, email o departamento.
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchUsers(string $search, int $limit): array;

    /**
     * Busca candidatos a revisor con permiso templates.review.
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchTemplateReviewerCandidates(string $search, int $limit): array;

    /**
     * Busca candidatos a revisor con permiso documents.review.
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchDocumentReviewerCandidates(string $search, int $limit): array;
}
