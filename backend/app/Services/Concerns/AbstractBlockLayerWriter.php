<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Support\BlockLayerPayloadComparator;

/**
 * Shared write algorithm for block layers across versioning domains
 * (template entity versions and document versions).
 *
 * Algorithm (syncLayersForNewPublication):
 *   1. Load the newly-created version snapshot and draft block DTOs.
 *   2. If no previous published version exists → write each block as a full
 *      override layer (inherits=false, removed=false).
 *   3. If a previous version exists → build a map of previous block payloads.
 *      For each draft block: compare payload; inherits=true when equal.
 *      For each previous block not in the draft: write a removed=true layer.
 *
 * Concrete subclasses supply:
 *   - loadCreatedVersionSnapshot(string $versionId): TVersionSnapshot
 *   - loadDraftBlocks(string $domainId): iterable<TBlockDto>
 *   - loadPreviousSnapshotBlockRows(TVersionSnapshot $createdVersion, string $domainId): list<array<string,mixed>>|null
 *     (null means no previous published version)
 *   - blockDtoId(TBlockDto $blockDto): string
 *   - blockDtoSortOrder(TBlockDto $blockDto): int
 *   - blockDtoPayload(TBlockDto $blockDto): array<string,mixed>
 *   - buildLayerAttributes(TVersionSnapshot $createdVersion, string $blockId, int $sortOrder, bool $inherits, bool $removed, ?array $payload): array<string,mixed>
 *   - persistLayer(array<string,mixed> $attributes): void
 *
 * @template TVersionSnapshot
 * @template TBlockDto
 */
abstract class AbstractBlockLayerWriter
{
    /**
     * Synchronize block layers for a new version publication.
     * Accepts scalar IDs; all model access is delegated to abstract methods.
     *
     * @param  string  $createdVersionId  The newly published version id.
     * @param  string  $domainId          The owning entity / document id.
     */
    final public function syncLayersForNewPublication(string $createdVersionId, string $domainId): void
    {
        /** @var TVersionSnapshot $createdVersion */
        $createdVersion = $this->loadCreatedVersionSnapshot($createdVersionId);

        $draftBlocks = $this->loadDraftBlocks($domainId);

        $previousBlockRows = $this->loadPreviousSnapshotBlockRows($createdVersion, $domainId);

        if ($previousBlockRows === null) {
            // First publication: store every block as a full override.
            foreach ($draftBlocks as $block) {
                $payload = $this->blockDtoPayload($block);
                $this->persistLayer($this->buildLayerAttributes(
                    createdVersion: $createdVersion,
                    blockId: $this->blockDtoId($block),
                    sortOrder: $this->blockDtoSortOrder($block),
                    inherits: false,
                    removed: false,
                    payload: $payload,
                ));
            }

            return;
        }

        /** @var array<string, array<string, mixed>> $prevById */
        $prevById = [];
        foreach ($previousBlockRows as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $prevById[$row['id']] = $row;
            }
        }

        $draftIds = [];
        foreach ($draftBlocks as $block) {
            $blockId = $this->blockDtoId($block);
            $draftIds[] = $blockId;

            $payload = $this->blockDtoPayload($block);
            $prev = $prevById[$blockId] ?? null;

            $inherits = $prev !== null && BlockLayerPayloadComparator::equal($prev, $payload);

            $this->persistLayer($this->buildLayerAttributes(
                createdVersion: $createdVersion,
                blockId: $blockId,
                sortOrder: $this->blockDtoSortOrder($block),
                inherits: $inherits,
                removed: false,
                payload: $inherits ? null : $payload,
            ));
        }

        // Blocks that existed previously but are absent from the draft → removed.
        foreach ($prevById as $id => $_prevRow) {
            if (! in_array($id, $draftIds, true)) {
                $this->persistLayer($this->buildLayerAttributes(
                    createdVersion: $createdVersion,
                    blockId: $id,
                    sortOrder: 0,
                    inherits: false,
                    removed: true,
                    payload: null,
                ));
            }
        }
    }

    // ─── Abstract methods — domain-specific contracts ─────────────────────────

    /**
     * Load the newly-created version snapshot DTO.
     *
     * @return TVersionSnapshot
     */
    abstract protected function loadCreatedVersionSnapshot(string $versionId): mixed;

    /**
     * Load the draft block DTOs for the owning entity / document.
     *
     * @return iterable<TBlockDto>
     */
    abstract protected function loadDraftBlocks(string $domainId): iterable;

    /**
     * Load the previous published snapshot block rows (normalized as list<array>),
     * or null if there is no previous published version.
     *
     * @param  TVersionSnapshot  $createdVersion
     * @return list<array<string, mixed>>|null
     */
    abstract protected function loadPreviousSnapshotBlockRows(mixed $createdVersion, string $domainId): ?array;

    /** @param TBlockDto $blockDto */
    abstract protected function blockDtoId(mixed $blockDto): string;

    /** @param TBlockDto $blockDto */
    abstract protected function blockDtoSortOrder(mixed $blockDto): int;

    /**
     * @param  TBlockDto  $blockDto
     * @return array<string, mixed>
     */
    abstract protected function blockDtoPayload(mixed $blockDto): array;

    /**
     * Build the attributes array for a layer row.
     *
     * @param  TVersionSnapshot        $createdVersion
     * @param  array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    abstract protected function buildLayerAttributes(
        mixed $createdVersion,
        string $blockId,
        int $sortOrder,
        bool $inherits,
        bool $removed,
        ?array $payload,
    ): array;

    /**
     * Persist a layer row.
     *
     * @param  array<string, mixed>  $attributes
     */
    abstract protected function persistLayer(array $attributes): void;
}
