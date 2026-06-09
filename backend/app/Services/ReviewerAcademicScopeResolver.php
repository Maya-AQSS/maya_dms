<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Users\ReviewerAcademicAssignmentScope;
use App\DTOs\Users\ReviewerCandidateFilterDto;
use App\Enums\TemplateVisibilityLevel;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;

/**
 * Resuelve el ámbito académico aplicable a candidatos validadores según visibilidad
 * de plantilla/documento. `global` y `personal` no aplican filtro (null).
 */
final class ReviewerAcademicScopeResolver
{
    public function __construct(
        private readonly AcademicHierarchyRepositoryInterface $academicHierarchyRepository,
    ) {}

    /**
     * @return ReviewerAcademicAssignmentScope|null null = sin filtro académico
     */
    public function resolveFromFilter(ReviewerCandidateFilterDto $filter): ?ReviewerAcademicAssignmentScope
    {
        return $this->resolve(
            $filter->visibilityLevel,
            $filter->studyTypeId,
            $filter->studyId,
            $filter->moduleId,
            $filter->teamId,
        );
    }

    /**
     * @return ReviewerAcademicAssignmentScope|null null = sin filtro académico
     */
    public function resolve(
        ?string $visibilityLevel,
        ?string $studyTypeId,
        ?string $studyId,
        ?string $moduleId,
        ?string $teamId,
    ): ?ReviewerAcademicAssignmentScope {
        $level = TemplateVisibilityLevel::tryFrom((string) $visibilityLevel);

        if ($level === null || $level === TemplateVisibilityLevel::Global || $level === TemplateVisibilityLevel::Personal) {
            return null;
        }

        return match ($level) {
            TemplateVisibilityLevel::Team => $this->resolveTeamScope($teamId),
            TemplateVisibilityLevel::StudyType => $this->resolveStudyTypeScope($studyTypeId),
            TemplateVisibilityLevel::Study => $this->resolveStudyScope($studyId, $studyTypeId),
            TemplateVisibilityLevel::Module => $this->resolveModuleScope($moduleId),
            default => null,
        };
    }

    private function resolveTeamScope(?string $teamId): ReviewerAcademicAssignmentScope
    {
        if ($teamId === null || $teamId === '') {
            return new ReviewerAcademicAssignmentScope;
        }

        return new ReviewerAcademicAssignmentScope(teamIds: [$teamId]);
    }

    private function resolveStudyTypeScope(?string $studyTypeId): ReviewerAcademicAssignmentScope
    {
        if ($studyTypeId === null || $studyTypeId === '') {
            return new ReviewerAcademicAssignmentScope;
        }

        return new ReviewerAcademicAssignmentScope(studyTypeIds: [$studyTypeId]);
    }

    private function resolveStudyScope(?string $studyId, ?string $studyTypeId): ReviewerAcademicAssignmentScope
    {
        if ($studyId === null || $studyId === '') {
            return new ReviewerAcademicAssignmentScope;
        }

        $resolvedStudyTypeId = $studyTypeId;
        if ($resolvedStudyTypeId === null || $resolvedStudyTypeId === '') {
            $resolvedStudyTypeId = $this->academicHierarchyRepository->findStudyTypeIdByStudyId($studyId);
        }

        return new ReviewerAcademicAssignmentScope(
            studyIds: [$studyId],
            studyTypeIds: $resolvedStudyTypeId !== null && $resolvedStudyTypeId !== '' ? [$resolvedStudyTypeId] : [],
        );
    }

    private function resolveModuleScope(?string $moduleId): ReviewerAcademicAssignmentScope
    {
        if ($moduleId === null || $moduleId === '') {
            return new ReviewerAcademicAssignmentScope;
        }

        $hierarchy = $this->academicHierarchyRepository->findStudyAndTypeByModuleId($moduleId);

        return new ReviewerAcademicAssignmentScope(
            moduleIds: [$moduleId],
            studyIds: $hierarchy !== null ? [$hierarchy['study_id']] : [],
            studyTypeIds: $hierarchy !== null ? [$hierarchy['study_type_id']] : [],
        );
    }
}
