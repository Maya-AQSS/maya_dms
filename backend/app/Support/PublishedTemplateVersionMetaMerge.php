<?php

namespace App\Support;

/**
 * Regla única para comparar metadatos de versión publicada entre
 * {@see \App\Models\EntityVersion} y {@see \App\Models\TemplateVersion} (legacy).
 *
 * Usada por {@see \App\Repositories\Eloquent\TemplateVersionRepository::findLatestPublishedMetaForTemplate}.
 * Listados como {@see \App\Services\TemplateService::listPublishedVersions} deduplican por número con la misma prioridad.
 */
final class PublishedTemplateVersionMetaMerge
{
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
