<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Documents\DocumentAcademicListFilter;
use App\DTOs\Documents\DocumentFilterDto;
use App\DTOs\Users\JwtProfileDto;
use App\Services\Contracts\UserProfileServiceInterface;

/**
 * Resuelve el filtro académico del listado de documentos a partir de query params
 * o del contexto académico del perfil ({@see UserProfileServiceInterface::getProfile()}).
 */
final class DocumentAcademicListFilterResolver
{
    public function __construct(
        private readonly UserProfileServiceInterface $userProfileService,
    ) {}

    public function resolve(
        DocumentFilterDto $filter,
        string $userId,
        JwtProfileDto $jwtProfile,
    ): ?DocumentAcademicListFilter {
        if ($filter->profileAcademicDefault) {
            $profile = $this->userProfileService->getProfile($userId, $jwtProfile);

            return $this->fromProfileScopes(
                $profile->studyTypeIds,
                $profile->studyIds,
                $profile->moduleIds,
            );
        }

        return $this->fromExplicitParams(
            $filter->studyTypeId,
            $filter->studyId,
            $filter->moduleId,
            $filter->studyTypeIds,
            $filter->studyIds,
            $filter->moduleIds,
        );
    }

    /**
     * @param  list<string>  $studyTypeIds
     * @param  list<string>  $studyIds
     * @param  list<string>  $moduleIds
     */
    private function fromProfileScopes(array $studyTypeIds, array $studyIds, array $moduleIds): ?DocumentAcademicListFilter
    {
        $studyTypeIds = $this->normalizeIds($studyTypeIds);
        $studyIds = $this->normalizeIds($studyIds);
        $moduleIds = $this->normalizeIds($moduleIds);

        $total = count($studyTypeIds) + count($studyIds) + count($moduleIds);
        if ($total === 0) {
            return null;
        }

        if ($total === 1) {
            return new DocumentAcademicListFilter(
                DocumentAcademicListFilter::MODE_CASCADE,
                $studyTypeIds,
                $studyIds,
                $moduleIds,
            );
        }

        return new DocumentAcademicListFilter(
            DocumentAcademicListFilter::MODE_UNION,
            $studyTypeIds,
            $studyIds,
            $moduleIds,
        );
    }

    /**
     * @param  list<string>|null  $studyTypeIds
     * @param  list<string>|null  $studyIds
     * @param  list<string>|null  $moduleIds
     */
    private function fromExplicitParams(
        ?string $studyTypeId,
        ?string $studyId,
        ?string $moduleId,
        ?array $studyTypeIds,
        ?array $studyIds,
        ?array $moduleIds,
    ): ?DocumentAcademicListFilter {
        $types = $this->mergeIds($studyTypeId, $studyTypeIds);
        $studies = $this->mergeIds($studyId, $studyIds);
        $modules = $this->mergeIds($moduleId, $moduleIds);

        if ($types === [] && $studies === [] && $modules === []) {
            return null;
        }

        return new DocumentAcademicListFilter(
            DocumentAcademicListFilter::MODE_CASCADE,
            $types,
            $studies,
            $modules,
        );
    }

    /**
     * @param  list<string>|null  $csvIds
     * @return list<string>
     */
    private function mergeIds(?string $singleId, ?array $csvIds): array
    {
        $ids = $this->normalizeIds($csvIds ?? []);

        if ($singleId !== null && $singleId !== '') {
            $ids[] = $singleId;
        }

        return $this->normalizeIds($ids);
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id): string => trim((string) $id), $ids),
            static fn (string $id): bool => $id !== '',
        )));
    }
}
