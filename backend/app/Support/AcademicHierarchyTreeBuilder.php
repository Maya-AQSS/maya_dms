<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CourseModule;
use App\Models\Study;
use App\Models\StudyType;
use Throwable;

/**
 * Construye el árbol anidado (tipo → estudio → módulo) a partir del contexto
 * académico del usuario (ids de asignaciones) resolviendo nombres en el
 * catálogo local (`study_types`, `studies`, `course_modules`).
 */
final class AcademicHierarchyTreeBuilder
{
    /**
     * @param  array{
     *   study_types: list<array{id: string, code: string, name: string}>,
     *   studies: list<array{id: string, code: string, name: string, study_type_id: string}>,
     *   modules: list<array{id: string, code: string, name: string}>,
     * }  $context
     * @return list<array<string, mixed>>
     */
    public function build(array $context): array
    {
        $studyTypeIds = array_merge(
            array_map(static fn (array $row): string => (string) $row['id'], $context['study_types']),
            array_map(static fn (array $row): string => (string) $row['study_type_id'], $context['studies']),
        );
        $studyIds = array_map(static fn (array $row): string => (string) $row['id'], $context['studies']);
        $moduleIds = array_map(static fn (array $row): string => (string) $row['id'], $context['modules']);

        $catalog = $this->loadCatalogNames($studyTypeIds, $studyIds, $moduleIds);

        /** @var array<string, array{id: string, name: string, studies: list<array<string, mixed>>}> $studyTypeNodes */
        $studyTypeNodes = [];
        /** @var array<string, array{0: string, 1: int}> $studyIndex */
        $studyIndex = [];

        foreach ($context['study_types'] as $studyType) {
            $id = (string) $studyType['id'];
            $studyTypeNodes[$id] = [
                'id' => $id,
                'name' => $this->resolveStudyTypeName($id, (string) ($studyType['name'] ?? ''), $catalog['study_types']),
                'studies' => [],
            ];
        }

        foreach ($context['studies'] as $study) {
            $this->appendStudy($studyTypeNodes, $studyIndex, $study, $catalog);
        }

        if ($moduleIds !== []) {
            foreach ($context['modules'] as $module) {
                $moduleId = (string) $module['id'];
                $studyId = $catalog['module_study_ids'][$moduleId] ?? '';
                if ($studyId === '') {
                    continue;
                }

                if (! isset($studyIndex[$studyId])) {
                    $hydrated = $this->hydrateStudyFromCatalog($studyTypeNodes, $studyIndex, $studyId, $catalog);
                    if (! $hydrated) {
                        continue;
                    }
                }

                [$typeId, $studyIdx] = $studyIndex[$studyId];
                $studyTypeNodes[$typeId]['studies'][$studyIdx]['course_modules'][] = [
                    'id' => $moduleId,
                    'name' => $this->resolveModuleDisplayName($moduleId, $module, $catalog['modules']),
                    'study_id' => $studyId,
                ];
            }
        }

        return array_values($studyTypeNodes);
    }

    /**
     * @param  list<string>  $studyTypeIds
     * @param  list<string>  $studyIds
     * @param  list<string>  $moduleIds
     * @return array{
     *   study_types: array<string, string>,
     *   studies: array<string, string>,
     *   modules: array<string, string>,
     *   module_study_ids: array<string, string>,
     * }
     */
    private function loadCatalogNames(array $studyTypeIds, array $studyIds, array $moduleIds): array
    {
        $catalog = [
            'study_types' => [],
            'studies' => [],
            'modules' => [],
            'module_study_ids' => [],
        ];

        try {
            $typeIds = $this->uniqueNonEmpty($studyTypeIds);
            if ($typeIds !== []) {
                $catalog['study_types'] = StudyType::query()
                    ->whereIn('id', $typeIds)
                    ->pluck('name', 'id')
                    ->map(static fn (mixed $name): string => trim((string) $name))
                    ->filter(static fn (string $name): bool => $name !== '')
                    ->all();
            }

            $normalizedStudyIds = $this->uniqueNonEmpty($studyIds);
            if ($normalizedStudyIds !== []) {
                $catalog['studies'] = Study::query()
                    ->whereIn('id', $normalizedStudyIds)
                    ->pluck('name', 'id')
                    ->map(static fn (mixed $name): string => trim((string) $name))
                    ->filter(static fn (string $name): bool => $name !== '')
                    ->all();
            }

            $normalizedModuleIds = $this->uniqueNonEmpty($moduleIds);
            if ($normalizedModuleIds !== []) {
                $rows = CourseModule::query()
                    ->whereIn('id', $normalizedModuleIds)
                    ->get(['id', 'name', 'study_id']);

                foreach ($rows as $row) {
                    $id = (string) $row->id;
                    $name = trim((string) $row->name);
                    if ($name !== '') {
                        $catalog['modules'][$id] = $name;
                    }
                    $catalog['module_study_ids'][$id] = (string) $row->study_id;
                }
            }
        } catch (Throwable) {
            // Sin catálogo: el árbol usará ids como fallback.
        }

        return $catalog;
    }

    /**
     * @param  array<string, array{id: string, name: string, studies: list<array<string, mixed>>}>  $studyTypeNodes
     * @param  array<string, array{0: string, 1: int}>  $studyIndex
     * @param  array{id: string, code: string, name: string, study_type_id: string}  $study
     * @param  array{study_types: array<string, string>, studies: array<string, string>, modules: array<string, string>, module_study_ids: array<string, string>}  $catalog
     */
    private function appendStudy(
        array &$studyTypeNodes,
        array &$studyIndex,
        array $study,
        array $catalog,
    ): void {
        $studyId = (string) $study['id'];
        if (isset($studyIndex[$studyId])) {
            return;
        }

        $typeId = (string) $study['study_type_id'];
        $this->ensureStudyTypeNode($studyTypeNodes, $typeId, $catalog['study_types']);

        $studyTypeNodes[$typeId]['studies'][] = [
            'id' => $studyId,
            'name' => $this->resolveStudyDisplayName($studyId, $study, $catalog['studies']),
            'study_type_id' => $typeId,
            'course_modules' => [],
        ];
        $studyIndex[$studyId] = [$typeId, count($studyTypeNodes[$typeId]['studies']) - 1];
    }

    /**
     * @param  array<string, array{id: string, name: string, studies: list<array<string, mixed>>}>  $studyTypeNodes
     * @param  array<string, array{0: string, 1: int}>  $studyIndex
     * @param  array{study_types: array<string, string>, studies: array<string, string>, modules: array<string, string>, module_study_ids: array<string, string>}  $catalog
     */
    private function hydrateStudyFromCatalog(
        array &$studyTypeNodes,
        array &$studyIndex,
        string $studyId,
        array $catalog,
    ): bool {
        $study = Study::query()->find($studyId);
        if ($study === null) {
            return false;
        }

        $catalog['studies'][$studyId] = trim((string) $study->name);

        $this->appendStudy($studyTypeNodes, $studyIndex, [
            'id' => (string) $study->id,
            'code' => (string) $study->id,
            'name' => (string) $study->name,
            'study_type_id' => (string) $study->study_type_id,
        ], $catalog);

        return isset($studyIndex[$studyId]);
    }

    /**
     * @param  array<string, array{id: string, name: string, studies: list<array<string, mixed>>}>  $studyTypeNodes
     * @param  array<string, string>  $studyTypeCatalogNames
     */
    private function ensureStudyTypeNode(
        array &$studyTypeNodes,
        string $typeId,
        array $studyTypeCatalogNames,
    ): void {
        if (isset($studyTypeNodes[$typeId])) {
            return;
        }

        $studyTypeNodes[$typeId] = [
            'id' => $typeId,
            'name' => $this->resolveStudyTypeName($typeId, '', $studyTypeCatalogNames),
            'studies' => [],
        ];
    }

    /**
     * @param  array<string, string>  $catalogNames
     */
    private function resolveStudyTypeName(string $id, string $fromContext, array $catalogNames): string
    {
        $contextName = trim($fromContext);
        if ($contextName !== '' && $contextName !== $id) {
            return $contextName;
        }

        $catalog = trim($catalogNames[$id] ?? '');
        if ($catalog !== '') {
            return $catalog;
        }

        return self::studyTypeLabel($id);
    }

    /**
     * @param  array{id: string, code: string, name: string, study_type_id: string}  $study
     * @param  array<string, string>  $catalogStudies
     */
    private function resolveStudyDisplayName(string $id, array $study, array $catalogStudies): string
    {
        $fromContext = trim((string) ($study['name'] ?? ''));
        if ($fromContext !== '' && $fromContext !== $id) {
            return $fromContext;
        }

        $fromCatalog = trim($catalogStudies[$id] ?? '');
        if ($fromCatalog !== '' && $fromCatalog !== $id) {
            return $fromCatalog;
        }

        $fromCode = trim((string) ($study['code'] ?? ''));
        if ($fromCode !== '' && $fromCode !== $id) {
            return $fromCode;
        }

        return $id;
    }

    /**
     * @param  array{id: string, code: string, name: string}  $module
     * @param  array<string, string>  $catalogModules
     */
    private function resolveModuleDisplayName(string $id, array $module, array $catalogModules): string
    {
        $fromContext = trim((string) ($module['name'] ?? ''));
        if ($fromContext !== '' && $fromContext !== $id) {
            return $fromContext;
        }

        $fromCatalog = trim($catalogModules[$id] ?? '');
        if ($fromCatalog !== '' && $fromCatalog !== $id) {
            return $fromCatalog;
        }

        $fromCode = trim((string) ($module['code'] ?? ''));
        if ($fromCode !== '' && $fromCode !== $id) {
            return $fromCode;
        }

        return $id;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function uniqueNonEmpty(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): string => (string) $id, $ids),
            static fn (string $id): bool => $id !== '',
        )));
    }

    private static function studyTypeLabel(string $grade): string
    {
        return match ($grade) {
            'GS' => 'Grado Superior',
            'GM' => 'Grado Medio',
            'NG' => 'Régimen General',
            default => $grade,
        };
    }
}
