<?php

namespace App\Services;

use App\Models\TemplateVersion;
use App\Models\TemplateVersionBlockLayer;

/**
 * Reconstruye el snapshot efectivo de bloques solo desde capas incrementales (sin usar blocks_snapshot).
 * Si {@see TemplateVersion::$blocks_snapshot} es null, se usan bloques desde {@see TemplateVersion::blocksSnapshotRows()}
 * (entity_versions enlazada).
 */
final class TemplateVersionBlockLayerResolver
{
    /**
     * Reconstruye el snapshot efectivo de bloques solo desde capas incrementales (sin usar blocks_snapshot).
     * 
     * @param string $templateVersionId ID de la versión de plantilla.
     * @return list<array<string, mixed>> El snapshot efectivo de bloques.
     */
    public function resolveBlocksSnapshot(string $templateVersionId): array
    {
        $version = TemplateVersion::query()->findOrFail($templateVersionId);

        $layers = TemplateVersionBlockLayer::query()
            ->where('template_version_id', $templateVersionId)
            ->orderBy('sort_order')
            ->orderBy('template_block_id')
            ->get();

        if ($layers->isEmpty()) {
            return $version->blocksSnapshotRows();
        }

        $out = [];
        foreach ($layers as $layer) {
            if ($layer->removed) {
                continue;
            }

            $eff = $this->effectiveBlockPayload((string) $layer->template_block_id, $version);
            if ($eff !== null) {
                $out[] = $eff;
            }
        }

        return $out;
    }

    /**
     * Calcula el payload efectivo de un bloque desde capas incrementales.
     * 
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $templateBlockId, TemplateVersion $version): ?array
    {
        $layer = TemplateVersionBlockLayer::query()
            ->where('template_version_id', $version->id)
            ->where('template_block_id', $templateBlockId)
            ->first();

        if ($layer === null) {
            return $this->blockFromLegacySnapshotOnly($version, $templateBlockId);
        }

        if ($layer->removed) {
            return null;
        }

        if ($layer->inherits_from_previous_publication) {
            if ($version->version_number <= 1) {
                return is_array($layer->override_payload) ? $layer->override_payload : null;
            }

            $parent = TemplateVersion::query()
                ->where('template_id', $version->template_id)
                ->where('version_number', $version->version_number - 1)
                ->firstOrFail();

            return $this->effectiveBlockPayload($templateBlockId, $parent);
        }

        return is_array($layer->override_payload) ? $layer->override_payload : null;
    }

    /**
     * Obtiene el payload de un bloque desde el snapshot de la versión anterior.
     * 
     * @return array<string, mixed>|null
     */
    private function blockFromLegacySnapshotOnly(TemplateVersion $version, string $templateBlockId): ?array
    {
        foreach ($version->blocksSnapshotRows() as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $templateBlockId) {
                return $b;
            }
        }

        return null;
    }
}
