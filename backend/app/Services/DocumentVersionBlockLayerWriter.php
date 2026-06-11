<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\DocumentBlockPayloadDto;
use App\DTOs\Documents\DocumentVersionSnapshotDto;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;
use App\Services\Concerns\AbstractBlockLayerWriter;

/**
 * Capas incrementales por versión publicada de documento (convive con snapshot JSON completo).
 * Accepts scalar IDs and DTOs, not Eloquent models.
 *
 * @extends AbstractBlockLayerWriter<DocumentVersionSnapshotDto, DocumentBlockPayloadDto>
 */
final class DocumentVersionBlockLayerWriter extends AbstractBlockLayerWriter
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentVersionRepositoryInterface $documentVersionRepository,
        private readonly DocumentVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    // ─── Domain-specific implementations ─────────────────────────────────────

    protected function loadCreatedVersionSnapshot(string $versionId): DocumentVersionSnapshotDto
    {
        return $this->documentVersionRepository->findOrFailAsSnapshot($versionId);
    }

    protected function loadDraftBlocks(string $domainId): iterable
    {
        return $this->documentRepository->findBlocksAsPayloadDtosForDocument($domainId);
    }

    protected function loadPreviousSnapshotBlockRows(mixed $createdVersion, string $domainId): ?array
    {
        $previous = $this->documentVersionRepository->findByDocumentAndVersionNumberAsSnapshot(
            $domainId,
            $createdVersion->versionNumber - 1,
        );

        if ($previous === null) {
            return null;
        }

        $blocks = $previous->snapshotData['blocks'] ?? null;

        return is_array($blocks) ? array_values($blocks) : [];
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
            'document_version_id' => $createdVersion->id,
            'document_block_id' => $blockId,
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
