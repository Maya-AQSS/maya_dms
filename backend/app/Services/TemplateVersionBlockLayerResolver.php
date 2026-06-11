<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Versioning\EntityVersionSnapshotDto;
use App\DTOs\Versioning\VersionBlockLayerDto;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Services\Concerns\AbstractBlockLayerResolver;
use Illuminate\Support\Collection;

/**
 * Reconstruye el snapshot efectivo de bloques desde capas incrementales.
 * Accepts scalar IDs and DTOs, not Eloquent models.
 *
 * @extends AbstractBlockLayerResolver<EntityVersionSnapshotDto, VersionBlockLayerDto>
 */
final class TemplateVersionBlockLayerResolver extends AbstractBlockLayerResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    // ─── Domain-specific snapshot accessors ───────────────────────────────────

    protected function loadSnapshotByVersionId(string $versionId): EntityVersionSnapshotDto
    {
        return $this->entityVersionRepository->findOrFailAsSnapshot($versionId);
    }

    protected function loadParentSnapshot(mixed $snapshotDto): ?EntityVersionSnapshotDto
    {
        return $this->entityVersionRepository->findOrFailPublishedByEntityAndNumberAsSnapshot(
            Template::class,
            $snapshotDto->entityId,
            $snapshotDto->versionNumber - 1,
        );
    }

    protected function loadLayersForVersion(string $versionId): Collection
    {
        return $this->layerRepository->listForVersionAsDto($versionId);
    }

    protected function loadLayerForVersionAndBlock(string $versionId, string $blockId): ?VersionBlockLayerDto
    {
        return $this->layerRepository->findForVersionAndBlockAsDto($versionId, $blockId);
    }

    protected function snapshotBlockRows(mixed $snapshotDto): array
    {
        return array_values($snapshotDto->blocksSnapshotRows);
    }

    protected function snapshotId(mixed $snapshotDto): string
    {
        return $snapshotDto->id;
    }

    protected function snapshotVersionNumber(mixed $snapshotDto): int
    {
        return $snapshotDto->versionNumber;
    }

    protected function layerBlockId(mixed $layerDto): string
    {
        return $layerDto->blockId;
    }

    protected function layerRemoved(mixed $layerDto): bool
    {
        return $layerDto->removed;
    }

    protected function layerInherits(mixed $layerDto): bool
    {
        return $layerDto->inheritsFromPreviousPublication;
    }

    protected function layerOverridePayload(mixed $layerDto): ?array
    {
        return $layerDto->overridePayload;
    }
}
