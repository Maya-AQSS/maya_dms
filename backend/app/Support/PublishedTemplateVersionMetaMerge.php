<?php

namespace App\Support;

/**
 * Regla única para comparar la última versión publicada de plantilla entre
 * {@see \App\Models\EntityVersion} y {@see \App\Models\TemplateVersion} (legacy).
 *
 * Debe mantenerse alineada con {@see \App\Services\TemplateService::listPublishedVersions}:
 * misma prioridad a entity_versions cuando el número de versión coincide.
 */
final class PublishedTemplateVersionMetaMerge
{
    /**
     * Prefiere la versión más reciente entre entity_versions y template_versions.
     */
    public static function preferLatestVersionNumber(?int $entityVersionNumber, ?int $legacyVersionNumber): ?int
    {
        if ($entityVersionNumber === null && $legacyVersionNumber === null) {
            return null;
        }
        if ($entityVersionNumber === null) {
            return $legacyVersionNumber;
        }
        if ($legacyVersionNumber === null) {
            return $entityVersionNumber;
        }

        return max($entityVersionNumber, $legacyVersionNumber);
    }

    /**
     * Prefiere la versión más reciente entre entity_versions y template_versions.
     *
     * @param  array{id: string, version_number: int, changelog: string}|null  $entityMeta
     * @param  array{id: string, version_number: int, changelog: string}|null  $legacyMeta
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public static function preferLatestMeta(?array $entityMeta, ?array $legacyMeta): ?array
    {
        if ($entityMeta === null) {
            return $legacyMeta;
        }
        if ($legacyMeta === null) {
            return $entityMeta;
        }
        if ($entityMeta['version_number'] > $legacyMeta['version_number']) {
            return $entityMeta;
        }
        if ($legacyMeta['version_number'] > $entityMeta['version_number']) {
            return $legacyMeta;
        }

        return $entityMeta;
    }
}
