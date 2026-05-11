<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\Enums\TemplateVisibilityLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the academic/team context fields for a new document based on the
 * anchored template's visibility level. Extracted from DocumentService::create().
 */
class TemplateContextResolver
{
    /**
     * @param  array<string, mixed>|null  $templateMeta
     * @return array{studyTypeId: ?string, studyId: ?string, moduleId: ?string, teamId: ?string}
     */
    public function resolve(CreateDocumentDto $dto, ?array $templateMeta): array
    {
        if ($templateMeta === null) {
            return [
                'studyTypeId' => $dto->studyTypeId,
                'studyId' => $dto->studyId,
                'moduleId' => $dto->moduleId,
                'teamId' => $dto->teamId,
            ];
        }

        $visibility = $templateMeta['visibility_level'] ?? null;
        $isTeamScopedTemplate = $visibility === TemplateVisibilityLevel::Team->value;

        $templateStudyTypeId = $this->nullableString($templateMeta['study_type_id'] ?? null);
        $templateStudyId     = $this->nullableString($templateMeta['study_id'] ?? null);
        $templateModuleId    = $this->nullableString($templateMeta['module_id'] ?? null);

        $studyTypeId = $dto->studyTypeId;
        $studyId     = $dto->studyId;
        $moduleId    = $dto->moduleId;
        $teamId      = $dto->teamId;

        if ($isTeamScopedTemplate) {
            $templateTeamId = $this->nullableString($templateMeta['team_id'] ?? null);
            if ($templateTeamId === null) {
                throw ValidationException::withMessages([
                    'template_id' => ['La plantilla de equipo no tiene un equipo válido asociado.'],
                ]);
            }
            return ['studyTypeId' => null, 'studyId' => null, 'moduleId' => null, 'teamId' => $templateTeamId];
        }

        if ($visibility === TemplateVisibilityLevel::Personal->value) {
            if (
                ($dto->studyTypeId !== null && $dto->studyTypeId !== $templateStudyTypeId) ||
                ($dto->studyId !== null && $dto->studyId !== $templateStudyId) ||
                ($dto->moduleId !== null && $dto->moduleId !== $templateModuleId) ||
                $dto->teamId !== null
            ) {
                throw ValidationException::withMessages([
                    'template_id' => ['Las plantillas personales no permiten cambiar el contexto académico al crear documentos.'],
                ]);
            }
            return [
                'studyTypeId' => $templateStudyTypeId,
                'studyId'     => $templateStudyId,
                'moduleId'    => $templateModuleId,
                'teamId'      => null,
            ];
        }

        if ($visibility === TemplateVisibilityLevel::Module->value) {
            if ($dto->teamId !== null) {
                throw ValidationException::withMessages([
                    'team_id' => ['Las plantillas de módulo no permiten asignar equipo al documento.'],
                ]);
            }
            if ($templateModuleId === null) {
                throw ValidationException::withMessages([
                    'template_id' => ['La plantilla de módulo no tiene un módulo válido asociado.'],
                ]);
            }
            if ($dto->moduleId !== null && $dto->moduleId !== $templateModuleId) {
                throw ValidationException::withMessages([
                    'module_id' => ['El documento debe crearse en el mismo módulo de la plantilla.'],
                ]);
            }
            return [
                'studyTypeId' => $templateStudyTypeId,
                'studyId'     => $templateStudyId,
                'moduleId'    => $templateModuleId,
                'teamId'      => null,
            ];
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

        return compact('studyTypeId', 'studyId', 'moduleId', 'teamId');
    }

    /** @return array{studyTypeId: ?string, studyId: ?string, moduleId: ?string, teamId: ?string} */
    private function resolveStudyContext(CreateDocumentDto $dto, ?string $templateStudyTypeId, ?string $templateStudyId): array
    {
        if ($dto->teamId !== null) {
            throw ValidationException::withMessages([
                'team_id' => ['Las plantillas de estudio no permiten asignar equipo al documento.'],
            ]);
        }
        if ($templateStudyId === null) {
            throw ValidationException::withMessages([
                'template_id' => ['La plantilla de estudio no tiene un estudio válido asociado.'],
            ]);
        }
        if ($dto->studyId !== null && $dto->studyId !== $templateStudyId) {
            throw ValidationException::withMessages([
                'study_id' => ['El documento debe crearse en el mismo estudio o en un módulo de ese estudio.'],
            ]);
        }

        $moduleId = null;
        $studyId  = $templateStudyId;

        if ($dto->moduleId !== null) {
            $moduleStudyId = DB::table('course_modules')
                ->where('id', $dto->moduleId)
                ->value('study_id');
            if (! is_string($moduleStudyId) || $moduleStudyId !== $templateStudyId) {
                throw ValidationException::withMessages([
                    'module_id' => ['El módulo debe pertenecer al mismo estudio de la plantilla.'],
                ]);
            }
            $moduleId = $dto->moduleId;
        }

        return [
            'studyTypeId' => $templateStudyTypeId,
            'studyId'     => $studyId,
            'moduleId'    => $moduleId,
            'teamId'      => null,
        ];
    }

    /** @return array{studyTypeId: ?string, studyId: ?string, moduleId: ?string, teamId: ?string} */
    private function resolveStudyTypeContext(CreateDocumentDto $dto, ?string $templateStudyTypeId): array
    {
        if ($dto->teamId !== null) {
            throw ValidationException::withMessages([
                'team_id' => ['Las plantillas por tipo de estudio no permiten asignar equipo al documento.'],
            ]);
        }
        if ($templateStudyTypeId === null) {
            throw ValidationException::withMessages([
                'template_id' => ['La plantilla por tipo de estudio no tiene un study_type válido asociado.'],
            ]);
        }
        if ($dto->studyTypeId !== null && $dto->studyTypeId !== $templateStudyTypeId) {
            throw ValidationException::withMessages([
                'study_type_id' => ['El documento debe crearse en el mismo tipo de estudio o en niveles inferiores.'],
            ]);
        }

        if ($dto->moduleId !== null) {
            $module = DB::table('course_modules')
                ->join('studies', 'studies.id', '=', 'course_modules.study_id')
                ->where('course_modules.id', $dto->moduleId)
                ->select('course_modules.study_id', 'studies.study_type_id')
                ->first();
            if (! $module || (string) $module->study_type_id !== $templateStudyTypeId) {
                throw ValidationException::withMessages([
                    'module_id' => ['El módulo debe pertenecer a un estudio del mismo tipo que la plantilla.'],
                ]);
            }
            if ($dto->studyId !== null && $dto->studyId !== (string) $module->study_id) {
                throw ValidationException::withMessages([
                    'study_id' => ['El estudio indicado no corresponde con el módulo seleccionado.'],
                ]);
            }
            return [
                'studyTypeId' => $templateStudyTypeId,
                'studyId'     => (string) $module->study_id,
                'moduleId'    => $dto->moduleId,
                'teamId'      => null,
            ];
        }

        if ($dto->studyId !== null) {
            $studyTypeFromStudy = DB::table('studies')
                ->where('id', $dto->studyId)
                ->value('study_type_id');
            if (! is_string($studyTypeFromStudy) || $studyTypeFromStudy !== $templateStudyTypeId) {
                throw ValidationException::withMessages([
                    'study_id' => ['El estudio debe pertenecer al mismo tipo de estudio de la plantilla.'],
                ]);
            }
            return [
                'studyTypeId' => $templateStudyTypeId,
                'studyId'     => $dto->studyId,
                'moduleId'    => null,
                'teamId'      => null,
            ];
        }

        return ['studyTypeId' => $templateStudyTypeId, 'studyId' => null, 'moduleId' => null, 'teamId' => null];
    }

    /** @return array{studyTypeId: ?string, studyId: ?string, moduleId: ?string, teamId: ?string} */
    private function resolveGlobalContext(CreateDocumentDto $dto): array
    {
        if ($dto->teamId !== null) {
            if ($dto->studyTypeId !== null || $dto->studyId !== null || $dto->moduleId !== null) {
                throw ValidationException::withMessages([
                    'team_id' => ['En plantillas globales, selecciona equipo o contexto académico, pero no ambos a la vez.'],
                ]);
            }
            $isTeamMember = DB::table('team_members')
                ->where('team_id', $dto->teamId)
                ->where('user_id', $dto->createdBy)
                ->exists();
            if (! $isTeamMember) {
                throw ValidationException::withMessages([
                    'team_id' => ['Solo miembros del equipo seleccionado pueden crear este documento en ese equipo.'],
                ]);
            }
            return ['studyTypeId' => null, 'studyId' => null, 'moduleId' => null, 'teamId' => $dto->teamId];
        }

        if ($dto->moduleId !== null) {
            $module = DB::table('course_modules')
                ->join('studies', 'studies.id', '=', 'course_modules.study_id')
                ->where('course_modules.id', $dto->moduleId)
                ->select('course_modules.study_id', 'studies.study_type_id')
                ->first();
            if (! $module || ! is_string($module->study_id) || ! is_string($module->study_type_id)) {
                throw ValidationException::withMessages([
                    'module_id' => ['El módulo seleccionado no existe.'],
                ]);
            }
            if ($dto->studyId !== null && $dto->studyId !== (string) $module->study_id) {
                throw ValidationException::withMessages([
                    'study_id' => ['El estudio indicado no corresponde con el módulo seleccionado.'],
                ]);
            }
            if ($dto->studyTypeId !== null && $dto->studyTypeId !== (string) $module->study_type_id) {
                throw ValidationException::withMessages([
                    'study_type_id' => ['El tipo de estudio indicado no corresponde con el módulo seleccionado.'],
                ]);
            }
            return [
                'studyTypeId' => (string) $module->study_type_id,
                'studyId'     => (string) $module->study_id,
                'moduleId'    => $dto->moduleId,
                'teamId'      => null,
            ];
        }

        if ($dto->studyId !== null) {
            $studyTypeFromStudy = DB::table('studies')
                ->where('id', $dto->studyId)
                ->value('study_type_id');
            if (! is_string($studyTypeFromStudy) || $studyTypeFromStudy === '') {
                throw ValidationException::withMessages([
                    'study_id' => ['El estudio seleccionado no existe.'],
                ]);
            }
            if ($dto->studyTypeId !== null && $dto->studyTypeId !== $studyTypeFromStudy) {
                throw ValidationException::withMessages([
                    'study_type_id' => ['El tipo de estudio indicado no corresponde con el estudio seleccionado.'],
                ]);
            }
            return [
                'studyTypeId' => $studyTypeFromStudy,
                'studyId'     => $dto->studyId,
                'moduleId'    => null,
                'teamId'      => null,
            ];
        }

        if ($dto->studyTypeId !== null) {
            return ['studyTypeId' => $dto->studyTypeId, 'studyId' => null, 'moduleId' => null, 'teamId' => null];
        }

        return ['studyTypeId' => null, 'studyId' => null, 'moduleId' => null, 'teamId' => null];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
