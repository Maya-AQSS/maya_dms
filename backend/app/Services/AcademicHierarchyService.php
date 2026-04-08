<?php

namespace App\Services;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use Illuminate\Support\Facades\Cache;

class AcademicHierarchyService implements AcademicHierarchyServiceInterface
{
    private AcademicHierarchyRepositoryInterface $hierarchyRepository;

    public function __construct(AcademicHierarchyRepositoryInterface $hierarchyRepository)
    {
        $this->hierarchyRepository = $hierarchyRepository;
    }

    public function getCachedTree(): array
    {
        return Cache::store('redis')->remember('academic_hierarchy_tree', 3600, function () {
            return $this->hierarchyRepository->getTree()->toArray();
        });
    }
}
