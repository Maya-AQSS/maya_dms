<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Collection;

/**
 * Shared resolution algorithm for block layers across versioning domains
 * (template entity versions and document versions).
 *
 * The algorithm is identical in both domains:
 *   1. resolveBlocksSnapshot: list layers, skip removed, call effectiveBlockPayload per block
 *   2. effectiveBlockPayload: if no layer → blockFromSnapshotOnly; if removed → null;
 *      if inherits + versionNumber > 1 → fetch parent and recurse; else overridePayload
 *   3. blockFromSnapshotOnly: linear scan of snapshot rows by block id
 *
 * Concrete subclasses supply the domain-specific:
 *   - Snapshot DTO type (via type parameters, enforced by abstract methods)
 *   - snapshotBlockRows($snapshotDto): normalized list<array<string,mixed>>
 *   - snapshotEntityId($snapshotDto): string (the owning entity / document id)
 *   - snapshotId($snapshotDto): string
 *   - snapshotVersionNumber($snapshotDto): int
 *   - loadSnapshotByVersionId(string $versionId): mixed (domain snapshot DTO)
 *   - loadParentSnapshot(mixed $snapshotDto): mixed|null
 *   - loadLayersForVersion(string $versionId): collection (of domain layer DTOs)
 *   - loadLayerForVersionAndBlock(string $versionId, string $blockId): mixed|null (domain layer DTO or null)
 *   - layerBlockId(mixed $layerDto): string
 *   - layerRemoved(mixed $layerDto): bool
 *   - layerInherits(mixed $layerDto): bool
 *   - layerOverridePayload(mixed $layerDto): array<string,mixed>|null
 *
 * @template TSnapshot
 * @template TLayer
 */
abstract class AbstractBlockLayerResolver
{
    // ─── Public entry point ───────────────────────────────────────────────────

    /**
     * Resolve the effective ordered list of block payloads for a version.
     *
     * @return list<array<string, mixed>>
     */
    final public function resolveBlocksSnapshot(string $versionId): array
    {
        /** @var TSnapshot $version */
        $version = $this->loadSnapshotByVersionId($versionId);

        $layers = $this->loadLayersForVersion($versionId);

        if ($layers->isEmpty()) {
            return $this->noLayersFallback($version);
        }

        $out = [];
        foreach ($layers as $layer) {
            if ($this->layerRemoved($layer)) {
                continue;
            }

            $blockId = $this->layerBlockId($layer);
            $eff = $this->effectiveBlockPayload($blockId, $version);
            if ($eff !== null) {
                $out[] = $eff;
            }
        }

        return $this->postProcess($out, $version);
    }

    // ─── Hooks for domain-specific behaviour ─────────────────────────────────

    /**
     * Called when there are no layers at all for the version.
     * Default: return snapshot block rows directly. Override to add post-processing.
     *
     * @param  TSnapshot  $snapshotDto
     * @return list<array<string, mixed>>
     */
    protected function noLayersFallback(mixed $snapshotDto): array
    {
        return array_values($this->snapshotBlockRows($snapshotDto));
    }

    /**
     * Called after building $out from layers.
     * Default: no-op (return as-is). Override to backfill structural fields etc.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  TSnapshot  $snapshotDto
     * @return list<array<string, mixed>>
     */
    protected function postProcess(array $blocks, mixed $snapshotDto): array
    {
        return $blocks;
    }

    // ─── Core algorithm ───────────────────────────────────────────────────────

    /**
     * @param  TSnapshot  $version
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $blockId, mixed $version): ?array
    {
        $layer = $this->loadLayerForVersionAndBlock($this->snapshotId($version), $blockId);

        if ($layer === null) {
            return $this->blockFromSnapshotOnly($version, $blockId);
        }

        if ($this->layerRemoved($layer)) {
            return null;
        }

        if ($this->layerInherits($layer)) {
            if ($this->snapshotVersionNumber($version) <= 1) {
                return $this->layerOverridePayload($layer);
            }

            $parent = $this->loadParentSnapshot($version);
            if ($parent === null) {
                return $this->layerOverridePayload($layer);
            }

            return $this->effectiveBlockPayload($blockId, $parent);
        }

        return $this->layerOverridePayload($layer);
    }

    /**
     * @param  TSnapshot  $version
     * @return array<string, mixed>|null
     */
    private function blockFromSnapshotOnly(mixed $version, string $blockId): ?array
    {
        foreach ($this->snapshotBlockRows($version) as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $blockId) {
                return $b;
            }
        }

        return null;
    }

    // ─── Abstract methods — domain-specific contracts ─────────────────────────

    /**
     * Load the domain snapshot DTO by version id.
     *
     * @return TSnapshot
     */
    abstract protected function loadSnapshotByVersionId(string $versionId): mixed;

    /**
     * Load the parent snapshot DTO (versionNumber - 1) or null if none.
     *
     * @param  TSnapshot  $snapshotDto
     * @return TSnapshot|null
     */
    abstract protected function loadParentSnapshot(mixed $snapshotDto): mixed;

    /**
     * Load all layer DTOs for the given version, ordered by sort_order.
     *
     * @return Collection<int, TLayer>
     */
    abstract protected function loadLayersForVersion(string $versionId): Collection;

    /**
     * Load a single layer DTO for a (versionId, blockId) pair, or null.
     *
     * @return TLayer|null
     */
    abstract protected function loadLayerForVersionAndBlock(string $versionId, string $blockId): mixed;

    /**
     * Extract the normalized block rows from the snapshot DTO.
     *
     * @param  TSnapshot  $snapshotDto
     * @return list<array<string, mixed>>
     */
    abstract protected function snapshotBlockRows(mixed $snapshotDto): array;

    /** @param TSnapshot $snapshotDto */
    abstract protected function snapshotId(mixed $snapshotDto): string;

    /** @param TSnapshot $snapshotDto */
    abstract protected function snapshotVersionNumber(mixed $snapshotDto): int;

    /** @param TLayer $layerDto */
    abstract protected function layerBlockId(mixed $layerDto): string;

    /** @param TLayer $layerDto */
    abstract protected function layerRemoved(mixed $layerDto): bool;

    /** @param TLayer $layerDto */
    abstract protected function layerInherits(mixed $layerDto): bool;

    /**
     * @param  TLayer  $layerDto
     * @return array<string, mixed>|null
     */
    abstract protected function layerOverridePayload(mixed $layerDto): ?array;
}
