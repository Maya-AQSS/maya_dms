<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateVersion;
use App\Models\TemplateVersionBlockLayer;

/**
 * Persistencia incremental de definición de bloques por versión publicada de plantilla.
 */
final class TemplateVersionBlockLayerWriter
{
    /**
     * Sincroniza capas de bloques para una nueva versión publicada de plantilla.
     *
     * @param TemplateVersion $createdVersion La versión publicada recién creada.
     * @param Template $template La plantilla a la que pertenece la versión.
     */
    public function syncLayersForNewPublication(TemplateVersion $createdVersion, Template $template): void
    {
        $template->loadMissing(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

        $previous = TemplateVersion::query()
            ->where('template_id', $createdVersion->template_id)
            ->where('version_number', $createdVersion->version_number - 1)
            ->first();

        $draftBlocks = $template->blocks;

        if ($previous === null) {
            foreach ($draftBlocks as $block) {
                $payload = $this->blockPayloadFromTemplateBlock($block);
                TemplateVersionBlockLayer::query()->create([
                    'template_version_id' => $createdVersion->id,
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
                'template_version_id' => $createdVersion->id,
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
                    'template_version_id' => $createdVersion->id,
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
     * Convierte un bloque de plantilla a su payload para persistencia.
     * 
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
