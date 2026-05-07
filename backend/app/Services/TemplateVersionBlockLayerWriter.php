<?php

namespace App\Services;

use App\Models\EntityVersion;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateVersionBlockLayer;

/**
 * Persistencia incremental de definición de bloques por publicación de plantilla en {@see entity_versions}.
 */
final class TemplateVersionBlockLayerWriter
{
    /**
     * Sincroniza capas de bloques para una nueva versión publicada de plantilla.
     */
    public function syncLayersForNewPublication(EntityVersion $createdVersion, Template $template): void
    {
        $template->loadMissing(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

        $previous = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $createdVersion->versionable_id)
            ->where('status', 'published')
            ->where('version_number', $createdVersion->version_number - 1)
            ->first();

        $draftBlocks = $template->blocks;

        if ($previous === null) {
            foreach ($draftBlocks as $block) {
                $payload = $this->blockPayloadFromTemplateBlock($block);
                TemplateVersionBlockLayer::query()->create([
                    'entity_version_id' => $createdVersion->id,
                    'template_block_id' => (string) $block->getKey(),
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
        foreach ($previous->blocksSnapshotRows() as $row) {
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $prevById[$row['id']] = $row;
            }
        }

        $draftIdStrings = $draftBlocks->map(static fn (TemplateBlock $b): string => (string) $b->getKey())->all();

        foreach ($draftBlocks as $block) {
            $payload = $this->blockPayloadFromTemplateBlock($block);
            $prev = $prevById[(string) $block->getKey()] ?? null;

            $inherits = $prev !== null && $this->payloadsEqual($prev, $payload);

            TemplateVersionBlockLayer::query()->create([
                'entity_version_id' => $createdVersion->id,
                'template_block_id' => (string) $block->getKey(),
                'sort_order' => (int) $block->sort_order,
                'inherits_from_previous_publication' => $inherits,
                'removed' => false,
                'override_payload' => $inherits ? null : $payload,
            ]);
        }

        foreach ($prevById as $id => $_prevRow) {
            if (! in_array((string) $id, $draftIdStrings, true)) {
                TemplateVersionBlockLayer::query()->create([
                    'entity_version_id' => $createdVersion->id,
                    'template_block_id' => (string) $id,
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
    private function blockPayloadFromTemplateBlock(TemplateBlock $block): array
    {
        $state = $block->block_state;

        return [
            'id' => $block->getKey(),
            'title' => $block->title,
            'description' => $block->description,
            'default_content' => $block->default_content,
            'block_state' => $state instanceof \BackedEnum ? $state->value : $state,
            'sort_order' => (int) $block->sort_order,
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
