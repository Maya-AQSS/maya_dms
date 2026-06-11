<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Support\BlockLayerPayloadComparator;

/**
 * Persistencia incremental de definición de bloques por publicación de plantilla.
 * Accepts scalar IDs and DTOs, not Eloquent models.
 */
final class TemplateVersionBlockLayerWriter
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    /**
     * Synchronize block layers for new version publication.
     * Accepts scalar IDs, fetches models in repository layer only.
     */
    public function syncLayersForNewPublication(string $createdVersionId, string $templateId): void
    {
        $createdVersion = $this->entityVersionRepository->findOrFailAsSnapshot($createdVersionId);
        $draftBlocks = $this->templateRepository->findBlocksAsPayloadDtosForTemplate($templateId);

        $previous = $this->entityVersionRepository->findPublishedByEntityAndNumberAsSnapshot(
            Template::class,
            $templateId,
            $createdVersion->versionNumber - 1,
        );

        if ($previous === null) {
            foreach ($draftBlocks as $block) {
                $payload = $block->toArray();
                $this->layerRepository->create([
                    'entity_version_id' => $createdVersion->id,
                    'template_block_id' => $block->blockId,
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
        foreach ($previous->blocksSnapshotRows as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $prevById[$row['id']] = $row;
            }
        }

        $draftIdStrings = $draftBlocks->map(static fn (TemplateBlockPayloadDto $b): string => $b->blockId)->all();

        foreach ($draftBlocks as $block) {
            $payload = $block->toArray();
            $prev = $prevById[$block->blockId] ?? null;

            $inherits = $prev !== null && BlockLayerPayloadComparator::equal($prev, $payload);

            $this->layerRepository->create([
                'entity_version_id' => $createdVersion->id,
                'template_block_id' => $block->blockId,
                'sort_order' => $block->sortOrder,
                'inherits_from_previous_publication' => $inherits,
                'removed' => false,
                'override_payload' => $inherits ? null : $payload,
            ]);
        }

        foreach ($prevById as $id => $_prevRow) {
            if (! in_array($id, $draftIdStrings, true)) {
                $this->layerRepository->create([
                    'entity_version_id' => $createdVersion->id,
                    'template_block_id' => $id,
                    'sort_order' => 0,
                    'inherits_from_previous_publication' => false,
                    'removed' => true,
                    'override_payload' => null,
                ]);
            }
        }
    }

}
