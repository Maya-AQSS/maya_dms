<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\DocumentVersionSnapshotDto;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;

/**
 * Reconstruye la lista efectiva de bloques del snapshot solo desde capas incrementales.
 * Accepts DTOs and scalar IDs, not Eloquent models.
 */
final class DocumentVersionBlockLayerResolver
{
    public function __construct(
        private readonly DocumentVersionRepositoryInterface $documentVersionRepository,
        private readonly DocumentVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function resolveBlocksSnapshot(string $documentVersionId): array
    {
        $version = $this->documentVersionRepository->findOrFailAsSnapshot($documentVersionId);

        $layers = $this->layerRepository->listForVersionAsDto($documentVersionId);

        if ($layers->isEmpty()) {
            $snap = $version->snapshotData;

            if (! isset($snap['blocks']) || ! is_array($snap['blocks'])) {
                return [];
            }

            return array_values($snap['blocks']);
        }

        $out = [];
        foreach ($layers as $layer) {
            if ($layer->removed) {
                continue;
            }

            $eff = $this->effectiveBlockPayload($layer->documentBlockId, $version);
            if ($eff !== null) {
                $out[] = $eff;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $documentBlockId, DocumentVersionSnapshotDto $version): ?array
    {
        $layer = $this->layerRepository->findForVersionAndBlockAsDto($version->id, $documentBlockId);

        if ($layer === null) {
            return $this->blockFromLegacySnapshotOnly($version, $documentBlockId);
        }

        if ($layer->removed) {
            return null;
        }

        if ($layer->inheritsFromPreviousPublication) {
            if ($version->versionNumber <= 1) {
                return $layer->overridePayload;
            }

            $parent = $this->documentVersionRepository->findOrFailByDocumentAndVersionNumberAsSnapshot(
                $version->documentId,
                $version->versionNumber - 1,
            );

            return $this->effectiveBlockPayload($documentBlockId, $parent);
        }

        return $layer->overridePayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blockFromLegacySnapshotOnly(DocumentVersionSnapshotDto $version, string $documentBlockId): ?array
    {
        $blocks = isset($version->snapshotData['blocks']) && is_array($version->snapshotData['blocks'])
            ? $version->snapshotData['blocks']
            : [];

        foreach ($blocks as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $documentBlockId) {
                return $b;
            }
        }

        return null;
    }
}
