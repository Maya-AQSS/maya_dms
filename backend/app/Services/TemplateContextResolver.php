<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\TemplateContextDto;
use App\Enums\TemplateVisibilityLevel;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the academic/team context fields for a new document based on the
 * anchored template's visibility level. Extracted from DocumentService::create().
 */
class TemplateContextResolver
{
    public function __construct(
        private readonly AcademicHierarchyRepositoryInterface $academicHierarchyRepository,
        private readonly TeamReadRepositoryInterface $teamReadRepository,
    ) {}

    /**
     * @param  array<string, mixed>|null  $templateMeta
     */
    public function resolve(CreateDocumentDto $dto, ?array $templateMeta): TemplateContextDto
    {
        if ($templateMeta === null) {
            return new TemplateContextDto(
                studyTypeId: $dto->studyTypeId,
                studyId: $dto->studyId,
                moduleId: $dto->moduleId,
                teamId: $dto->teamId,
            );
        }

        $visibility = $templateMeta['visibility_level'] ?? null;
        $isTeamScopedTemplate = $visibility === TemplateVisibilityLevel::Team->value;

        $templateStudyTypeId = $this->nullableString($templateMeta['study_type_id'] ?? null);
        $templateStudyId = $this->nullableString($templateMeta['study_id'] ?? null);
        $templateModuleId = $this->nullableString($templateMeta['module_id'] ?? null);

        $studyTypeId = $dto->studyTypeId;
        $studyId = $dto->studyId;
        $moduleId = $dto->moduleId;
        $teamId = $dto->teamId;

        if ($isTeamScopedTemplate) {
            $templateTeamId = $this->nullableString($templateMeta['team_id'] ?? null);
            if ($templateTeamId === null) {
                throw ValidationException::withMessages([
                    'template_id' => [__('validation.template_context.team_no_team')],
                ]);
            }

            return new TemplateContextDto(studyTypeId: null, studyId: null, moduleId: null, teamId: $templateTeamId);
        }

        if ($visibility === TemplateVisibilityLevel::Personal->value) {
            if (
                ($dto->studyTypeId !== null && $dto->studyTypeId !== $templateStudyTypeId) ||
                ($dto->studyId !== null && $dto->studyId !== $templateStudyId) ||
                ($dto->moduleId !== null && $dto->moduleId !== $templateModuleId) ||
                $dto->teamId !== null
            ) {
                throw ValidationException::withMessages([
                    'template_id' => [__('validation.template_context.personal_no_context_change')],
                ]);
            }

            return new TemplateContextDto(
                studyTypeId: $templateStudyTypeId,
                studyId: $templateStudyId,
                moduleId: $templateModuleId,
                teamId: null,
            );
        }

        if ($visibility === TemplateVisibilityLevel::Module->value) {
            if ($dto->teamId !== null) {
                throw ValidationException::withMessages([
                    'team_id' => [__('validation.template_context.module_no_team')],
                ]);
            }
            if ($templateModuleId === null) {
                throw ValidationException::withMessages([
                    'template_id' => [__('validation.template_context.module_no_module')],
                ]);
            }
            if ($dto->moduleId !== null && $dto->moduleId !== $templateModuleId) {
                throw ValidationException::withMessages([
                    'module_id' => [__('validation.template_context.module_same_module')],
                ]);
            }

            return new TemplateContextDto(
                studyTypeId: $templateStudyTypeId,
                studyId: $templateStudyId,
                moduleId: $templateModuleId,
                teamId: null,
            );
        }

        if ($visibility === TemplateVisibilityLevel::Study->value) {
            return $this->resolveStudyContext($dto, $templateStudyTypeId, $templateStudyId);
        }

        if ($visibility === TemplateVisibilityLevel::StudyType->value) {
            return $this->resolveStudyTypeContext($dto, $templateStudyTypeId);
        }

        if ($visibility === TemplateVisibilityLevel::Global->value) {
            return $this->resolveGlobalContext($dto);
        }

        return new TemplateContextDto(
            studyTypeId: $studyTypeId,
            studyId: $studyId,
            moduleId: $moduleId,
            teamId: $teamId,
        );
    }

    private function resolveStudyContext(CreateDocumentDto $dto, ?string $templateStudyTypeId, ?string $templateStudyId): TemplateContextDto
    {
        if ($dto->teamId !== null) {
            throw ValidationException::withMessages([
                'team_id' => [__('validation.template_context.study_no_team')],
            ]);
        }
        if ($templateStudyId === null) {
            throw ValidationException::withMessages([
                'template_id' => [__('validation.template_context.study_no_study')],
            ]);
        }
        if ($dto->studyId !== null && $dto->studyId !== $templateStudyId) {
            throw ValidationException::withMessages([
                'study_id' => [__('validation.template_context.study_same_study')],
            ]);
        }

        $moduleId = null;
        $studyId = $templateStudyId;

        if ($dto->moduleId !== null) {
            $moduleStudyId = $this->academicHierarchyRepository->findStudyIdByModuleId($dto->moduleId);
            if ($moduleStudyId === null || $moduleStudyId !== $templateStudyId) {
                throw ValidationException::withMessages([
                    'module_id' => [__('validation.template_context.study_module_same_study')],
                ]);
            }
            $moduleId = $dto->moduleId;
        }

        return new TemplateContextDto(
            studyTypeId: $templateStudyTypeId,
            studyId: $studyId,
            moduleId: $moduleId,
            teamId: null,
        );
    }

    private function resolveStudyTypeContext(CreateDocumentDto $dto, ?string $templateStudyTypeId): TemplateContextDto
    {
        if ($dto->teamId !== null) {
            throw ValidationException::withMessages([
                'team_id' => [__('validation.template_context.study_type_no_team')],
            ]);
        }
        if ($templateStudyTypeId === null) {
            throw ValidationException::withMessages([
                'template_id' => [__('validation.template_context.study_type_no_study_type')],
            ]);
        }
        if ($dto->studyTypeId !== null && $dto->studyTypeId !== $templateStudyTypeId) {
            throw ValidationException::withMessages([
                'study_type_id' => [__('validation.template_context.study_type_same_type')],
            ]);
        }

        if ($dto->moduleId !== null) {
            $module = $this->academicHierarchyRepository->findStudyAndTypeByModuleId($dto->moduleId);
            if ($module === null || $module['study_type_id'] !== $templateStudyTypeId) {
                throw ValidationException::withMessages([
                    'module_id' => [__('validation.template_context.study_type_module_same_type')],
                ]);
            }
            if ($dto->studyId !== null && $dto->studyId !== $module['study_id']) {
                throw ValidationException::withMessages([
                    'study_id' => [__('validation.template_context.study_not_match_module')],
                ]);
            }

            return new TemplateContextDto(
                studyTypeId: $templateStudyTypeId,
                studyId: $module['study_id'],
                moduleId: $dto->moduleId,
                teamId: null,
            );
        }

        if ($dto->studyId !== null) {
            $studyTypeFromStudy = $this->academicHierarchyRepository->findStudyTypeIdByStudyId($dto->studyId);
            if ($studyTypeFromStudy === null || $studyTypeFromStudy !== $templateStudyTypeId) {
                throw ValidationException::withMessages([
                    'study_id' => [__('validation.template_context.study_same_study_type')],
                ]);
            }

            return new TemplateContextDto(
                studyTypeId: $templateStudyTypeId,
                studyId: $dto->studyId,
                moduleId: null,
                teamId: null,
            );
        }

        return new TemplateContextDto(studyTypeId: $templateStudyTypeId, studyId: null, moduleId: null, teamId: null);
    }

    private function resolveGlobalContext(CreateDocumentDto $dto): TemplateContextDto
    {
        if ($dto->teamId !== null) {
            if ($dto->studyTypeId !== null || $dto->studyId !== null || $dto->moduleId !== null) {
                throw ValidationException::withMessages([
                    'team_id' => [__('validation.template_context.global_team_or_context')],
                ]);
            }
            if (! $this->teamReadRepository->isMember($dto->teamId, (string) $dto->createdBy)) {
                throw ValidationException::withMessages([
                    'team_id' => [__('validation.template_context.global_team_member')],
                ]);
            }

            return new TemplateContextDto(studyTypeId: null, studyId: null, moduleId: null, teamId: $dto->teamId);
        }

        if ($dto->moduleId !== null) {
            $module = $this->academicHierarchyRepository->findStudyAndTypeByModuleId($dto->moduleId);
            if ($module === null) {
                throw ValidationException::withMessages([
                    'module_id' => [__('validation.template_context.global_module_not_found')],
                ]);
            }
            if ($dto->studyId !== null && $dto->studyId !== $module['study_id']) {
                throw ValidationException::withMessages([
                    'study_id' => [__('validation.template_context.study_not_match_module')],
                ]);
            }
            if ($dto->studyTypeId !== null && $dto->studyTypeId !== $module['study_type_id']) {
                throw ValidationException::withMessages([
                    'study_type_id' => [__('validation.template_context.global_study_type_not_match_module')],
                ]);
            }

            return new TemplateContextDto(
                studyTypeId: $module['study_type_id'],
                studyId: $module['study_id'],
                moduleId: $dto->moduleId,
                teamId: null,
            );
        }

        if ($dto->studyId !== null) {
            $studyTypeFromStudy = $this->academicHierarchyRepository->findStudyTypeIdByStudyId($dto->studyId);
            if ($studyTypeFromStudy === null) {
                throw ValidationException::withMessages([
                    'study_id' => [__('validation.template_context.global_study_not_found')],
                ]);
            }
            if ($dto->studyTypeId !== null && $dto->studyTypeId !== $studyTypeFromStudy) {
                throw ValidationException::withMessages([
                    'study_type_id' => [__('validation.template_context.global_study_type_not_match_study')],
                ]);
            }

            return new TemplateContextDto(
                studyTypeId: $studyTypeFromStudy,
                studyId: $dto->studyId,
                moduleId: null,
                teamId: null,
            );
        }

        if ($dto->studyTypeId !== null) {
            return new TemplateContextDto(studyTypeId: $dto->studyTypeId, studyId: null, moduleId: null, teamId: null);
        }

        return new TemplateContextDto(studyTypeId: null, studyId: null, moduleId: null, teamId: null);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
