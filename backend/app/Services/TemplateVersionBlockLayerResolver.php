<?php

namespace App\Services;

use App\Models\EntityVersion;
use App\Models\Template;
use App\Models\TemplateVersionBlockLayer;

/**
 * Reconstruye el snapshot efectivo de bloques desde capas incrementales (sin duplicar JSON completo).
 */
final class TemplateVersionBlockLayerResolver
{
    /**
     * @return list<array<string, mixed>>
     */
    public function resolveBlocksSnapshot(string $entityVersionId): array
    {
        $version = EntityVersion::query()->findOrFail($entityVersionId);

        $layers = TemplateVersionBlockLayer::query()
            ->where('entity_version_id', $entityVersionId)
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
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $templateBlockId, EntityVersion $version): ?array
    {
        $layer = TemplateVersionBlockLayer::query()
            ->where('entity_version_id', $version->id)
            ->where('template_block_id', $templateBlockId)
            ->first();

        if ($layer === null) {
            return $this->blockFromSnapshotOnly($version, $templateBlockId);
        }

        if ($layer->removed) {
            return null;
        }

        if ($layer->inherits_from_previous_publication) {
            if ($version->version_number <= 1) {
                return is_array($layer->override_payload) ? $layer->override_payload : null;
            }

            $parent = EntityVersion::query()
                ->where('versionable_type', Template::class)
                ->where('versionable_id', $version->versionable_id)
                ->where('status', 'published')
                ->where('version_number', $version->version_number - 1)
                ->firstOrFail();

            return $this->effectiveBlockPayload($templateBlockId, $parent);
        }

        return is_array($layer->override_payload) ? $layer->override_payload : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blockFromSnapshotOnly(EntityVersion $version, string $templateBlockId): ?array
    {
        foreach ($version->blocksSnapshotRows() as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $templateBlockId) {
                return $b;
            }
        }

        return null;
    }
}
