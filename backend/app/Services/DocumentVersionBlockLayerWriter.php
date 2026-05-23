<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;

/**
 * Capas incrementales por versión publicada de documento (convive con snapshot JSON completo).
 */
final class DocumentVersionBlockLayerWriter
{
    public function __construct(
        private readonly DocumentVersionRepositoryInterface $documentVersionRepository,
        private readonly DocumentVersionBlockLayerRepositoryInterface $layerRepository,
    ) {}

    public function syncLayersForNewPublication(DocumentVersion $createdVersion, Document $document): void
    {
        $document->loadMissing(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

        $previous = $this->documentVersionRepository->findByDocumentAndVersionNumber(
            (string) $createdVersion->document_id,
            (int) $createdVersion->version_number - 1,
        );

        $draftBlocks = $document->blocks;

        if ($previous === null) {
            foreach ($draftBlocks as $block) {
                $payload = $this->blockPayloadFromDocumentBlock($block);
                $this->layerRepository->create([
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
        $snap = $previous->resolvedSnapshotData();
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

            $this->layerRepository->create([
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
                $this->layerRepository->create([
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
     * @return array<string, mixed>
     */
    private function blockPayloadFromDocumentBlock(DocumentBlock $block): array
    {
        $kind = $block->kind;

        return [
            'id' => $block->getKey(),
            'template_block_id' => $block->template_block_id,
            'content' => $block->content,
            'is_filled' => (bool) $block->is_filled,
            'sort_order' => (int) $block->sort_order,
            'last_edited_by' => $block->last_edited_by,
            'locked_by' => $block->locked_by,
            'locked_at' => $block->locked_at?->toIso8601String(),
            'kind' => $kind instanceof \BackedEnum
                ? $kind->value
                : (is_string($kind) && $kind !== '' ? $kind : \App\Enums\BlockKind::Content->value),
        ];
    }

    /**
     * @param  array<string, mixed>  $prev
     * @param  array<string, mixed>  $curr
     */
    private function payloadsEqual(array $prev, array $curr): bool
    {
        return json_encode($this->normalizeForCompare($prev)) === json_encode($this->normalizeForCompare($curr));
    }

    /**
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
