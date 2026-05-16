<?php
declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UserProfileRepositoryInterface
{
    /**
     * Perfil del usuario desde la vista FDW (`users`), siempre filtrado por id.
     */
    public function findById(string $userId): ?array;

    /**
     * Grupos académicos del usuario; el JOIN incluye filtro por user_id.
     *
     * @return list<array{id: string, name: string, description: ?string, role: string, is_department: bool}>
     */
    public function findTeamsByUserId(string $userId): array;

    /**
     * IDs de tipos de estudio asignados al usuario desde la vista `user_study_types`.
     *
     * @return list<string>
     */
    public function findStudyTypeIdsByUserId(string $userId): array;

    /**
     * IDs de estudios asignados al usuario desde la vista `user_studies`.
     *
     * @return list<string>
     */
    public function findStudyIdsByUserId(string $userId): array;

    /**
     * IDs de módulos de curso asignados al usuario desde la vista `user_course_modules`.
     *
     * @return list<string>
     */
    public function findModuleIdsByUserId(string $userId): array;
}
