<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;

/**
 * Reconstruye el snapshot efectivo de bloques desde capas incrementales.
 */
final class TemplateVersionBlockLayerResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function resolveBlocksSnapshot(string $entityVersionId): array
    {
        $version = $this->entityVersionRepository->findOrFail($entityVersionId);

        $layers = $this->layerRepository->listForVersion($entityVersionId);

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
        $layer = $this->layerRepository->findForVersionAndBlock((string) $version->id, $templateBlockId);

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

            $parent = $this->entityVersionRepository->findOrFailPublishedByEntityAndNumber(
                Template::class,
                (string) $version->versionable_id,
                (int) $version->version_number - 1,
            );

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
