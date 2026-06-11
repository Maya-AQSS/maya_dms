<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\DocumentVersionSnapshotDto;
use App\DTOs\Versioning\VersionBlockLayerDto;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;
use App\Services\Concerns\AbstractBlockLayerResolver;
use Illuminate\Support\Collection;

/**
 * Reconstruye la lista efectiva de bloques del snapshot solo desde capas incrementales.
 * Accepts DTOs and scalar IDs, not Eloquent models.
 *
 * @extends AbstractBlockLayerResolver<DocumentVersionSnapshotDto, VersionBlockLayerDto>
 */
final class DocumentVersionBlockLayerResolver extends AbstractBlockLayerResolver
{
    public function __construct(
        private readonly DocumentVersionRepositoryInterface $documentVersionRepository,
        private readonly DocumentVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    // ─── Domain-specific snapshot accessors ───────────────────────────────────

    protected function loadSnapshotByVersionId(string $versionId): DocumentVersionSnapshotDto
    {
        return $this->documentVersionRepository->findOrFailAsSnapshot($versionId);
    }

    protected function loadParentSnapshot(mixed $snapshotDto): ?DocumentVersionSnapshotDto
    {
        // findOrFail: si la versión padre no existe es una inconsistencia de
        // datos y debe aflorar, no degradar en silencio a override-only.
        return $this->documentVersionRepository->findOrFailByDocumentAndVersionNumberAsSnapshot(
            $snapshotDto->documentId,
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
        $blocks = $snapshotDto->snapshotData['blocks'] ?? null;

        return (is_array($blocks)) ? array_values($blocks) : [];
    }

    protected function noLayersFallback(mixed $snapshotDto): array
    {
        return $this->snapshotBlockRows($snapshotDto);
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
