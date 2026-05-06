<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\DocumentVersionBlockLayer;

/**
 * Reconstruye la lista efectiva de bloques del snapshot solo desde capas incrementales.
 * Convive con {@see DocumentVersion::$snapshot_data} hasta que las lecturas migren; si no hay capas, usa `snapshot_data['blocks']`.
 */
final class DocumentVersionBlockLayerResolver
{
    /**
     * Reconstruye el snapshot efectivo de bloques solo desde capas incrementales.
     *
     * @param string $documentVersionId ID de la versión de documento.
     * @return list<array<string, mixed>>
     */
    public function resolveBlocksSnapshot(string $documentVersionId): array
    {
        $version = DocumentVersion::query()->findOrFail($documentVersionId);

        $layers = DocumentVersionBlockLayer::query()
            ->where('document_version_id', $documentVersionId)
            ->orderBy('sort_order')
            ->orderBy('document_block_id')
            ->get();

        if ($layers->isEmpty()) {
            $snap = $version->snapshot_data;

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
     * Calcula el payload efectivo de un bloque desde capas incrementales.
     *
     * @param string $documentBlockId ID del bloque.
     * @param DocumentVersion $version La versión de documento.
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $documentBlockId, DocumentVersion $version): ?array
    {
        $layer = DocumentVersionBlockLayer::query()
            ->where('document_version_id', $version->id)
            ->where('document_block_id', $documentBlockId)
            ->first();

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

            $parent = DocumentVersion::query()
                ->where('document_id', $version->document_id)
                ->where('version_number', $version->version_number - 1)
                ->firstOrFail();

            return $this->effectiveBlockPayload($documentBlockId, $parent);
        }

        return is_array($layer->override_payload) ? $layer->override_payload : null;
    }

    /**
     * Obtiene el payload de un bloque desde el snapshot de la versión anterior.
     *
     * @param DocumentVersion $version La versión de documento.
     * @param string $documentBlockId ID del bloque.
     * @return array<string, mixed>|null
     */
    private function blockFromLegacySnapshotOnly(DocumentVersion $version, string $documentBlockId): ?array
    {
        $snap = $version->snapshot_data;
        $blocks = is_array($snap) && isset($snap['blocks']) && is_array($snap['blocks']) ? $snap['blocks'] : [];

        foreach ($blocks as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $documentBlockId) {
                return $b;
            }
        }

        return null;
    }
}
