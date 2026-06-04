<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Templates\EntityVersionSnapshotDto;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;

/**
 * Reconstruye el snapshot efectivo de bloques desde capas incrementales.
 * Accepts scalar IDs and DTOs, not Eloquent models.
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
        $version = $this->entityVersionRepository->findOrFailAsSnapshot($entityVersionId);

        $layers = $this->layerRepository->listForVersionAsDto($entityVersionId);

        if ($layers->isEmpty()) {
            return array_values($version->blocksSnapshotRows);
        }

        $out = [];
        foreach ($layers as $layer) {
            if ($layer->removed) {
                continue;
            }

            $eff = $this->effectiveBlockPayload($layer->templateBlockId, $version);
            if ($eff !== null) {
                $out[] = $eff;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $templateBlockId, EntityVersionSnapshotDto $version): ?array
    {
        $layer = $this->layerRepository->findForVersionAndBlockAsDto($version->id, $templateBlockId);

        if ($layer === null) {
            return $this->blockFromSnapshotOnly($version, $templateBlockId);
        }

        if ($layer->removed) {
            return null;
        }

        if ($layer->inheritsFromPreviousPublication) {
            if ($version->versionNumber <= 1) {
                return $layer->overridePayload;
            }

            $parent = $this->entityVersionRepository->findOrFailPublishedByEntityAndNumberAsSnapshot(
                Template::class,
                $version->entityId,
                $version->versionNumber - 1,
            );

            return $this->effectiveBlockPayload($templateBlockId, $parent);
        }

        return $layer->overridePayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blockFromSnapshotOnly(EntityVersionSnapshotDto $version, string $templateBlockId): ?array
    {
        foreach ($version->blocksSnapshotRows as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $templateBlockId) {
                return $b;
            }
        }

        return null;
    }
}
