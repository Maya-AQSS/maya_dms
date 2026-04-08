<?php

namespace App\Repositories\Eloquent;

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
}
