<?php
declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface AcademicHierarchyRepositoryInterface
{
    /**
     * Get the complete academic hierarchy tree
     */
    public function getTree(): Collection;

    /**
     * `study_id` del módulo, o null si no existe.
     */
    public function findStudyIdByModuleId(string $moduleId): ?string;

    /**
     * `(study_id, study_type_id)` del módulo (joined con studies), o null si no existe.
     *
     * @return array{study_id: string, study_type_id: string}|null
     */
    public function findStudyAndTypeByModuleId(string $moduleId): ?array;

    /**
     * `study_type_id` del estudio, o null si no existe.
     */
    public function findStudyTypeIdByStudyId(string $studyId): ?string;
}
