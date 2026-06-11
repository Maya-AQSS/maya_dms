<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\DocumentBlockPayloadDto;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;
use App\Support\BlockLayerPayloadComparator;

/**
 * Capas incrementales por versión publicada de documento (convive con snapshot JSON completo).
 * Accepts scalar IDs and DTOs, not Eloquent models.
 */
final class DocumentVersionBlockLayerWriter
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentVersionRepositoryInterface $documentVersionRepository,
        private readonly DocumentVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    /**
     * Synchronize block layers for new version publication.
     * Accepts scalar IDs, fetches models in repository layer only.
     */
    public function syncLayersForNewPublication(string $createdVersionId, string $documentId): void
    {
        $createdVersion = $this->documentVersionRepository->findOrFailAsSnapshot($createdVersionId);
        $draftBlocks = $this->documentRepository->findBlocksAsPayloadDtosForDocument($documentId);

        $previous = $this->documentVersionRepository->findByDocumentAndVersionNumberAsSnapshot(
            $documentId,
            $createdVersion->versionNumber - 1,
        );

        if ($previous === null) {
            foreach ($draftBlocks as $block) {
                $payload = $block->toArray();
                $this->layerRepository->create([
                    'document_version_id' => $createdVersion->id,
                    'document_block_id' => $block->blockId,
                    'sort_order' => $block->sortOrder,
                    'inherits_from_previous_publication' => false,
                    'removed' => false,
                    'override_payload' => $payload,
                ]);
            }

            return;
        }

        /** @var array<string, array<string, mixed>> $prevById */
        $prevById = [];
        $prevBlocks = isset($previous->snapshotData['blocks']) && is_array($previous->snapshotData['blocks'])
            ? $previous->snapshotData['blocks']
            : [];
        foreach ($prevBlocks as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $prevById[$row['id']] = $row;
            }
        }

        $draftIdStrings = $draftBlocks->map(static fn (DocumentBlockPayloadDto $b): string => $b->blockId)->all();

        foreach ($draftBlocks as $block) {
            $payload = $block->toArray();
            $prev = $prevById[$block->blockId] ?? null;

            $inherits = $prev !== null && BlockLayerPayloadComparator::equal($prev, $payload);

            $this->layerRepository->create([
                'document_version_id' => $createdVersion->id,
                'document_block_id' => $block->blockId,
                'sort_order' => $block->sortOrder,
                'inherits_from_previous_publication' => $inherits,
                'removed' => false,
                'override_payload' => $inherits ? null : $payload,
            ]);
        }

        foreach ($prevById as $id => $_prevRow) {
            if (! in_array($id, $draftIdStrings, true)) {
                $this->layerRepository->create([
                    'document_version_id' => $createdVersion->id,
                    'document_block_id' => $id,
                    'sort_order' => 0,
                    'inherits_from_previous_publication' => false,
                    'removed' => true,
                    'override_payload' => null,
                ]);
            }
        }
    }

}
