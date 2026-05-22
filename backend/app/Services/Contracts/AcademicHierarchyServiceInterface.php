<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface AcademicHierarchyServiceInterface
{
    /**
     * Get the cached complete academic hierarchy tree.
     *
     * @return list<array<string, mixed>>
     */
    public function getCachedTree(): array;

    /**
     * Devuelve el árbol académico para el usuario indicado.
     *
     * - Si el `$profile` tiene permisos globales (`admin`, `users.search`,
     *   `audit.read`) o `study_type_ids` contiene `'*'`, devuelve el catálogo
     *   completo (caché Redis).
     * - En caso contrario, monta el árbol solo con las asignaciones del usuario
     *   (`AcademicContextService` → tablas `user_*` + catálogo `studies` /
     *   `course_modules`).
     *
     * @param  array<string, mixed>  $profile  Perfil resuelto por
     *                                         `UserProfileService::getProfile()` (con `id`,
     *                                         `study_type_ids`, `permissions`, etc.).
     * @return list<array<string, mixed>>
     */
    public function getFilteredTreeForProfile(array $profile): array;
}
