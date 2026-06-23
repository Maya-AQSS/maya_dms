<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Users\ReviewerAcademicAssignmentScope;

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
    public function searchTemplateReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerAcademicAssignmentScope $academicScope = null,
    ): array;

    /**
     * Usuarios con permiso `documents.review` (validadores de documento).
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
     */
    public function searchDocumentReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerAcademicAssignmentScope $academicScope = null,
    ): array;

    /**
     * Devuelve los IDs de la lista que tienen asignación académica dentro del ámbito.
     *
     * @param  list<string>  $userIds
     * @return list<string>
     */
    public function filterUserIdsMatchingAcademicScope(array $userIds, ReviewerAcademicAssignmentScope $scope): array;

    /**
     * Nombre legible del usuario por su ID, o null si no existe / está vacío.
     */
    public function findNameById(string $userId): ?string;

    /**
     * Resuelve nombres para varios IDs en una sola consulta (evita N+1).
     * Devuelve un mapa id => nombre (trim), omitiendo los que no existen o
     * tienen nombre vacío. Las claves ausentes se interpretan como `null`.
     *
     * @param  list<string>  $userIds
     * @return array<string, string>
     */
    public function findNamesByIds(array $userIds): array;
}
