<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TemplateVisibilityLevel;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Normaliza los atributos académicos de una entidad (Template o Document)
 * según el scope de visibilidad de la plantilla que la rige.
 *
 * Extrae el bloque match sobre TemplateVisibilityLevel que era ~85% idéntico
 * en TemplateService::normalizeUpdateAttributesAgainstTemplateScope() y
 * DocumentService::normalizeUpdateAttributesAgainstDocumentAndTemplate().
 *
 * Las divergencias entre dominios se parametrizan mediante AcademicScopeContext:
 *   - $strictTemplateIds: Template siempre sobreescribe; Document solo si no-null.
 *   - Mensajes de error específicos de dominio.
 *
 * Las validaciones adicionales (owner, compatibilidad de template) permanecen
 * en cada Service y NO son responsabilidad de esta clase.
 */
final class AcademicScopeNormalizer
{
    /**
     * Normaliza los campos académicos del array $attributes según el nivel de
     * visibilidad contenido en $ctx.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalize(
        AcademicHierarchyRepositoryInterface $repo,
        AcademicScopeContext $ctx,
        array $attributes,
    ): array {
        $normalized = $attributes;
        $level = $ctx->visibilityLevel->value;

        // Resolver el valor efectivo de cada campo: lo que viene en $attributes
        // tiene prioridad; si no está, usar el valor actual de la entidad.
        $studyTypeId = array_key_exists('study_type_id', $attributes)
            ? $attributes['study_type_id']
            : $ctx->entityStudyTypeId;
        $studyId = array_key_exists('study_id', $attributes)
            ? $attributes['study_id']
            : $ctx->entityStudyId;
        $moduleId = array_key_exists('module_id', $attributes)
            ? $attributes['module_id']
            : $ctx->entityModuleId;

        // ── Personal / Team: anular todo scope académico ──────────────────────
        if ($level === TemplateVisibilityLevel::Personal->value
            || $level === TemplateVisibilityLevel::Team->value
        ) {
            $normalized['study_type_id'] = $ctx->templateStudyTypeId;
            $normalized['study_id'] = $ctx->templateStudyId;
            $normalized['module_id'] = $ctx->templateModuleId;

            return $normalized;
        }

        // ── Module ────────────────────────────────────────────────────────────
        if ($level === TemplateVisibilityLevel::Module->value) {
            if ($ctx->templateModuleId !== null) {
                if ($moduleId !== null && $moduleId !== $ctx->templateModuleId) {
                    throw ValidationException::withMessages([
                        'module_id' => [$ctx->onModuleConflict],
                    ]);
                }
                $normalized['module_id'] = $ctx->templateModuleId;
            } elseif ($ctx->strictTemplateIds) {
                // Template domain: siempre escribe null cuando templateModuleId es null.
                $normalized['module_id'] = null;
            }

            if ($ctx->strictTemplateIds) {
                $normalized['study_id'] = $ctx->templateStudyId;
                $normalized['study_type_id'] = $ctx->templateStudyTypeId;
            } else {
                if ($ctx->templateStudyId !== null) {
                    $normalized['study_id'] = $ctx->templateStudyId;
                }
                if ($ctx->templateStudyTypeId !== null) {
                    $normalized['study_type_id'] = $ctx->templateStudyTypeId;
                }
            }

            return $normalized;
        }

        // ── Study ─────────────────────────────────────────────────────────────
        if ($level === TemplateVisibilityLevel::Study->value) {
            if ($ctx->templateStudyId !== null) {
                if ($studyId !== null && $studyId !== $ctx->templateStudyId) {
                    throw ValidationException::withMessages([
                        'study_id' => [$ctx->onStudyConflict],
                    ]);
                }
                $normalized['study_id'] = $ctx->templateStudyId;
            } elseif ($ctx->strictTemplateIds) {
                $normalized['study_id'] = null;
            }

            if (is_string($moduleId) && $moduleId !== '') {
                $moduleStudyId = $repo->findStudyIdByModuleId($moduleId);
                if ($moduleStudyId === null || $moduleStudyId !== $ctx->templateStudyId) {
                    throw ValidationException::withMessages([
                        'module_id' => [$ctx->onModuleStudyMismatch],
                    ]);
                }
                $normalized['module_id'] = $moduleId;
            }

            if ($ctx->strictTemplateIds) {
                $normalized['study_type_id'] = $ctx->templateStudyTypeId;
            } elseif ($ctx->templateStudyTypeId !== null) {
                $normalized['study_type_id'] = $ctx->templateStudyTypeId;
            }

            return $normalized;
        }

        // ── StudyType ─────────────────────────────────────────────────────────
        if ($level === TemplateVisibilityLevel::StudyType->value) {
            if ($ctx->strictTemplateIds) {
                $normalized['study_type_id'] = $ctx->templateStudyTypeId;
            } elseif ($ctx->templateStudyTypeId !== null) {
                $normalized['study_type_id'] = $ctx->templateStudyTypeId;
            }

            if (is_string($moduleId) && $moduleId !== '') {
                $module = $repo->findStudyAndTypeByModuleId($moduleId);

                if ($module === null || $module['study_type_id'] !== $ctx->templateStudyTypeId) {
                    throw ValidationException::withMessages([
                        'module_id' => [$ctx->onModuleTypeMismatch],
                    ]);
                }

                if (is_string($studyId) && $studyId !== '' && $studyId !== $module['study_id']) {
                    throw ValidationException::withMessages([
                        'study_id' => [$ctx->onStudyModuleMismatch],
                    ]);
                }

                $normalized['module_id'] = $moduleId;
                $normalized['study_id'] = $module['study_id'];

                return $normalized;
            }

            if (is_string($studyId) && $studyId !== '') {
                $studyTypeFromStudy = $repo->findStudyTypeIdByStudyId($studyId);
                if ($studyTypeFromStudy === null || $studyTypeFromStudy !== $ctx->templateStudyTypeId) {
                    throw ValidationException::withMessages([
                        'study_id' => [$ctx->onStudyTypeMismatch],
                    ]);
                }
            }

            return $normalized;
        }

        // ── Global ────────────────────────────────────────────────────────────
        if ($level === TemplateVisibilityLevel::Global->value
            && is_string($moduleId) && $moduleId !== ''
        ) {
            $moduleStudyId = $repo->findStudyIdByModuleId($moduleId);
            if ($moduleStudyId === null) {
                throw ValidationException::withMessages([
                    'module_id' => [$ctx->onModuleNotFound],
                ]);
            }
            if (is_string($studyId) && $studyId !== '' && $studyId !== $moduleStudyId) {
                throw ValidationException::withMessages([
                    'study_id' => [$ctx->onStudyModuleMismatch],
                ]);
            }
        }

        return $normalized;
    }
}
