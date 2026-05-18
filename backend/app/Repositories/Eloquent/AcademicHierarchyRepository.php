<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CourseModule;
use App\Models\Study;
use App\Models\StudyType;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AcademicHierarchyRepository implements AcademicHierarchyRepositoryInterface
{
    /**
     * Árbol de jerarquía académica.
     */
    public function getTree(): Collection
    {
        return StudyType::with(['studies', 'studies.courseModules'])->get();
    }

    public function findStudyIdByModuleId(string $moduleId): ?string
    {
        $value = CourseModule::query()
            ->where('id', $moduleId)
            ->value('study_id');

        return is_string($value) ? $value : null;
    }

    public function findStudyAndTypeByModuleId(string $moduleId): ?array
    {
        $row = CourseModule::query()
            ->join('studies', 'studies.id', '=', 'course_modules.study_id')
            ->where('course_modules.id', $moduleId)
            ->select('course_modules.study_id', 'studies.study_type_id')
            ->first();

        if ($row === null) {
            return null;
        }

        $studyId = is_string($row->study_id) ? $row->study_id : null;
        $studyTypeId = is_string($row->study_type_id) ? $row->study_type_id : null;

        if ($studyId === null || $studyTypeId === null) {
            return null;
        }

        return ['study_id' => $studyId, 'study_type_id' => $studyTypeId];
    }

    public function findStudyTypeIdByStudyId(string $studyId): ?string
    {
        $value = Study::query()
            ->where('id', $studyId)
            ->value('study_type_id');

        return is_string($value) ? $value : null;
    }
}
