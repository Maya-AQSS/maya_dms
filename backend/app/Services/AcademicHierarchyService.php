<?php

namespace App\Services;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use Illuminate\Support\Facades\Cache;

class AcademicHierarchyService implements AcademicHierarchyServiceInterface
{
    public function __construct(
        private readonly AcademicHierarchyRepositoryInterface $hierarchyRepository,
    ) {}

    /**
     * Árbol de jerarquía académica, con caché Redis.
     */
    public function getCachedTree(): array
    {
        return Cache::store('redis')->remember('academic_hierarchy_tree', 3600, function () {
            return $this->hierarchyRepository->getTree()->toArray();
        });
    }
}
