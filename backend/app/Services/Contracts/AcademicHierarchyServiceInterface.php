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
     * Devuelve el árbol académico filtrado para el usuario indicado.
     *
     * - Si el `$profile` tiene permisos globales (`admin`, `users.search`,
     *   `audit.read`) o `study_type_ids` contiene `'*'`, devuelve el árbol
     *   completo.
     * - Si no tiene `study_type_ids` asignados, devuelve `[]`.
     * - Si tiene asignaciones parciales, filtra `studies` y `course_modules`
     *   intersectando con `study_ids` y `module_ids` respectivamente.
     *
     * @param  array<string, mixed>  $profile  Perfil resuelto por
     *   `UserProfileService::getProfile()` (con `study_type_ids`,
     *   `study_ids`, `module_ids`, `permissions`).
     *
     * @return list<array<string, mixed>>
     */
    public function getFilteredTreeForProfile(array $profile): array;
}
