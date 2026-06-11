<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\DTOs\Versioning\EntityVersionSnapshotDto;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Services\Concerns\AbstractBlockLayerWriter;

/**
 * Persistencia incremental de definición de bloques por publicación de plantilla.
 * Accepts scalar IDs and DTOs, not Eloquent models.
 *
 * @extends AbstractBlockLayerWriter<EntityVersionSnapshotDto, TemplateBlockPayloadDto>
 */
final class TemplateVersionBlockLayerWriter extends AbstractBlockLayerWriter
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    // ─── Domain-specific implementations ─────────────────────────────────────

    protected function loadCreatedVersionSnapshot(string $versionId): EntityVersionSnapshotDto
    {
        return $this->entityVersionRepository->findOrFailAsSnapshot($versionId);
    }

    protected function loadDraftBlocks(string $domainId): iterable
    {
        return $this->templateRepository->findBlocksAsPayloadDtosForTemplate($domainId);
    }

    protected function loadPreviousSnapshotBlockRows(mixed $createdVersion, string $domainId): ?array
    {
        $previous = $this->entityVersionRepository->findPublishedByEntityAndNumberAsSnapshot(
            Template::class,
            $domainId,
            $createdVersion->versionNumber - 1,
        );

        if ($previous === null) {
            return null;
        }

        return $previous->blocksSnapshotRows;
    }

    protected function blockDtoId(mixed $blockDto): string
    {
        return $blockDto->blockId;
    }

    protected function blockDtoSortOrder(mixed $blockDto): int
    {
        return $blockDto->sortOrder;
    }

    protected function blockDtoPayload(mixed $blockDto): array
    {
        return $blockDto->toArray();
    }

    protected function buildLayerAttributes(
        mixed $createdVersion,
        string $blockId,
        int $sortOrder,
        bool $inherits,
        bool $removed,
        ?array $payload,
    ): array {
        return [
            'entity_version_id' => $createdVersion->id,
            'template_block_id' => $blockId,
            'sort_order' => $sortOrder,
            'inherits_from_previous_publication' => $inherits,
            'removed' => $removed,
            'override_payload' => $payload,
        ];
    }

    protected function persistLayer(array $attributes): void
    {
        $this->layerRepository->create($attributes);
    }
}
