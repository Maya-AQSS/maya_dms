<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;

/**
 * Reconstruye la lista efectiva de bloques del snapshot solo desde capas incrementales.
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
        $version = $this->documentVersionRepository->findOrFail($documentVersionId);

        $layers = $this->layerRepository->listForVersion($documentVersionId);

        if ($layers->isEmpty()) {
            $snap = $version->resolvedSnapshotData();

            if (! is_array($snap) || ! isset($snap['blocks']) || ! is_array($snap['blocks'])) {
                return [];
            }

            return array_values($snap['blocks']);
        }

        $out = [];
        foreach ($layers as $layer) {
            if ($layer->removed) {
                continue;
            }

            $eff = $this->effectiveBlockPayload((string) $layer->document_block_id, $version);
            if ($eff !== null) {
                $out[] = $eff;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $documentBlockId, DocumentVersion $version): ?array
    {
        $layer = $this->layerRepository->findForVersionAndBlock((string) $version->id, $documentBlockId);

        if ($layer === null) {
            return $this->blockFromLegacySnapshotOnly($version, $documentBlockId);
        }

        if ($layer->removed) {
            return null;
        }

        if ($layer->inherits_from_previous_publication) {
            if ($version->version_number <= 1) {
                return is_array($layer->override_payload) ? $layer->override_payload : null;
            }

            $parent = $this->documentVersionRepository->findOrFailByDocumentAndVersionNumber(
                (string) $version->document_id,
                (int) $version->version_number - 1,
            );

            return $this->effectiveBlockPayload($documentBlockId, $parent);
        }

        return is_array($layer->override_payload) ? $layer->override_payload : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blockFromLegacySnapshotOnly(DocumentVersion $version, string $documentBlockId): ?array
    {
        $snap = $version->resolvedSnapshotData();
        $blocks = is_array($snap) && isset($snap['blocks']) && is_array($snap['blocks']) ? $snap['blocks'] : [];

        foreach ($blocks as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $documentBlockId) {
                return $b;
            }
        }

        return null;
    }
}
