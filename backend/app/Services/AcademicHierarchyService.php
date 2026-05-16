<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use Illuminate\Support\Facades\Cache;

class AcademicHierarchyService implements AcademicHierarchyServiceInterface
{
    public function __construct(
        private readonly AcademicHierarchyRepositoryInterface $hierarchyRepository,
    ) {}

    private const GLOBAL_PERMISSIONS = ['admin', 'users.search', 'audit.read'];

    /**
     * Árbol de jerarquía académica, con caché Redis.
     */
    public function getCachedTree(): array
    {
        return Cache::store('redis')->remember('academic_hierarchy_tree', 3600, function () {
            return $this->hierarchyRepository->getTree()->toArray();
        });
    }

    public function getFilteredTreeForProfile(array $profile): array
    {
        $tree = $this->getCachedTree();

        $allowedStudyTypeIds = $profile['study_type_ids'] ?? [];
        $allowedStudyIds     = $profile['study_ids'] ?? [];
        $allowedModuleIds    = $profile['module_ids'] ?? [];
        $permissions         = $profile['permissions'] ?? [];

        $hasGlobalPermission = (bool) array_intersect(self::GLOBAL_PERMISSIONS, $permissions);
        $hasWildcardStudyType = in_array('*', $allowedStudyTypeIds, true);

        if ($hasGlobalPermission || $hasWildcardStudyType) {
            return $tree;
        }

        if (empty($allowedStudyTypeIds)) {
            return [];
        }

        $filtered = [];
        foreach ($tree as $studyType) {
            if (! in_array((string) $studyType['id'], $allowedStudyTypeIds, true)) {
                continue;
            }

            $studies = [];
            foreach ($studyType['studies'] ?? [] as $study) {
                if (! empty($allowedStudyIds) && ! in_array((string) $study['id'], $allowedStudyIds, true)) {
                    continue;
                }

                $modules = [];
                foreach ($study['course_modules'] ?? [] as $module) {
                    if (empty($allowedModuleIds) || in_array((string) $module['id'], $allowedModuleIds, true)) {
                        $modules[] = $module;
                    }
                }
                $study['course_modules'] = $modules;
                $studies[] = $study;
            }
            $studyType['studies'] = $studies;
            $filtered[] = $studyType;
        }

        return $filtered;
    }
}
