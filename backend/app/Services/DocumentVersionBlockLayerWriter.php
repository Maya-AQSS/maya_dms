<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentVersion;
use App\Models\DocumentVersionBlockLayer;

/**
 * Capas incrementales por versión publicada de documento (convive con snapshot JSON completo).
 */
final class DocumentVersionBlockLayerWriter
{
    /**
     * Sincroniza capas de bloques para una nueva versión publicada de documento.
     *
     * @param DocumentVersion $createdVersion La versión publicada recién creada.
     * @param Document $document El documento al que pertenece la versión.
     */
    public function syncLayersForNewPublication(DocumentVersion $createdVersion, Document $document): void
    {
        $document->loadMissing(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

        $previous = DocumentVersion::query()
            ->where('document_id', $createdVersion->document_id)
            ->where('version_number', $createdVersion->version_number - 1)
            ->first();

        $draftBlocks = $document->blocks;

        if ($previous === null) {
            foreach ($draftBlocks as $block) {
                $payload = $this->blockPayloadFromDocumentBlock($block);
                DocumentVersionBlockLayer::query()->create([
                    'document_version_id' => $createdVersion->id,
                    'document_block_id' => (string) $block->getKey(),
                    'sort_order' => (int) $block->sort_order,
                    'inherits_from_previous_publication' => false,
                    'removed' => false,
                    'override_payload' => $payload,
                ]);
            }

            return;
        }

        /** @var array<string, array<string, mixed>> $prevById */
        $prevById = [];
        $snap = $previous->snapshot_data;
        $prevBlocks = is_array($snap) && isset($snap['blocks']) && is_array($snap['blocks']) ? $snap['blocks'] : [];
        foreach ($prevBlocks as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $prevById[$row['id']] = $row;
            }
        }

        $draftIdStrings = $draftBlocks->map(static fn (DocumentBlock $b): string => (string) $b->getKey())->all();

        foreach ($draftBlocks as $block) {
            $payload = $this->blockPayloadFromDocumentBlock($block);
            $prev = $prevById[(string) $block->getKey()] ?? null;

            $inherits = $prev !== null && $this->payloadsEqual($prev, $payload);

            DocumentVersionBlockLayer::query()->create([
                'document_version_id' => $createdVersion->id,
                'document_block_id' => (string) $block->getKey(),
                'sort_order' => (int) $block->sort_order,
                'inherits_from_previous_publication' => $inherits,
                'removed' => false,
                'override_payload' => $inherits ? null : $payload,
            ]);
        }

        foreach ($prevById as $id => $_prevRow) {
            if (! in_array((string) $id, $draftIdStrings, true)) {
                DocumentVersionBlockLayer::query()->create([
                    'document_version_id' => $createdVersion->id,
                    'document_block_id' => (string) $id,
                    'sort_order' => 0,
                    'inherits_from_previous_publication' => false,
                    'removed' => true,
                    'override_payload' => null,
                ]);
            }
        }
    }

    /**
     * Convierte un bloque de documento a su payload para persistencia.
     * 
     * @return array<string, mixed>
     */
    private function blockPayloadFromDocumentBlock(DocumentBlock $block): array
    {
        return [
            'id' => $block->getKey(),
            'template_block_id' => $block->template_block_id,
            'content' => $block->content,
            'is_filled' => (bool) $block->is_filled,
            'sort_order' => (int) $block->sort_order,
            'last_edited_by' => $block->last_edited_by,
            'locked_by' => $block->locked_by,
            'locked_at' => $block->locked_at?->toIso8601String(),
        ];
    }

    /**
     * Compara payloads de bloques para determinar si son iguales.
     * 
     * @param  array<string, mixed>  $prev
     * @param  array<string, mixed>  $curr
     */
    private function payloadsEqual(array $prev, array $curr): bool
    {
        return json_encode($this->normalizeForCompare($prev)) === json_encode($this->normalizeForCompare($curr));
    }

    /**
     * Normaliza un payload de bloque para comparación (ordena claves y subarrays).
     * 
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeForCompare(array $data): array
    {
        ksort($data);
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->normalizeForCompare($v);
            }
        }

        return $data;
    }
}
