<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use App\Support\AcademicHierarchyTreeBuilder;
use Illuminate\Support\Facades\Cache;
use Maya\Profile\Services\Contracts\AcademicContextServiceInterface;

class AcademicHierarchyService implements AcademicHierarchyServiceInterface
{
    private const GLOBAL_PERMISSIONS = ['admin', 'users.search', 'audit.read'];

    public function __construct(
        private readonly AcademicHierarchyRepositoryInterface $hierarchyRepository,
        private readonly AcademicContextServiceInterface $academicContextService,
        private readonly AcademicHierarchyTreeBuilder $treeBuilder,
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

    public function getFilteredTreeForProfile(array $profile): array
    {
        $allowedStudyTypeIds = $profile['study_type_ids'] ?? [];
        $permissions = $profile['permissions'] ?? [];

        $hasGlobalPermission = (bool) array_intersect(self::GLOBAL_PERMISSIONS, $permissions);
        $hasWildcardStudyType = in_array('*', $allowedStudyTypeIds, true);

        if ($hasGlobalPermission || $hasWildcardStudyType) {
            return $this->getCachedTree();
        }

        $userId = (string) ($profile['id'] ?? '');
        if ($userId === '') {
            return [];
        }

        $context = $this->academicContextService->forUser($userId)->toArray();

        return $this->treeBuilder->build([
            'study_types' => $context['study_types'],
            'studies' => $context['studies'],
            'modules' => $context['modules'],
        ]);
    }
}
