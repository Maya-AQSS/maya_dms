<?php
declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UserDirectoryRepositoryInterface
{
    /**
     * Busca usuarios por nombre o email.
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array;

    /**
     * Usuarios con permiso `templates.review` (validadores de plantilla).
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchTemplateReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array;

    /**
     * Usuarios con permiso `documents.review` (validadores de documento).
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchDocumentReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array;

    /**
     * Nombre legible del usuario por su ID, o null si no existe / está vacío.
     */
    public function findNameById(string $userId): ?string;
}
