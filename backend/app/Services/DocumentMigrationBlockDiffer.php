<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BlockState;

/**
 * Lógica pura de comparación entre los bloques de dos versiones publicadas de
 * una plantilla, enriquecida con el contenido real del documento origen.
 *
 * El mapeo antiguo→nuevo se realiza por {@code template_block_id} (estable entre
 * versiones). No accede a base de datos: recibe filas ya resueltas.
 */
final class DocumentMigrationBlockDiffer
{
    /**
     * @param  list<array<string, mixed>>  $sourceBlocks   Bloques de la versión anclada al documento origen.
     * @param  list<array<string, mixed>>  $targetBlocks   Bloques de la última versión publicada.
     * @param  array<string, mixed>  $oldContentByBlock     Contenido real del documento origen, indexado por template_block_id.
     * @return list<array<string, mixed>>
     */
    public function diff(array $sourceBlocks, array $targetBlocks, array $oldContentByBlock): array
    {
        $sourceById = $this->indexById($sourceBlocks);
        $targetById = $this->indexById($targetBlocks);

        $targetSorted = collect($targetBlocks)
            ->sortBy(fn (array $b): int => (int) ($b['sort_order'] ?? 0))
            ->values()
            ->all();

        $out = [];

        foreach ($targetSorted as $target) {
            $id = (string) ($target['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $old = $sourceById[$id] ?? null;
            $targetState = (string) ($target['block_state'] ?? BlockState::Editable->value);
            $oldState = $old !== null ? (string) ($old['block_state'] ?? BlockState::Editable->value) : null;

            $out[] = [
                'template_block_id' => $id,
                'title' => $target['title'] ?? null,
                'type' => $target['type'] ?? 'text',
                'sort_order' => (int) ($target['sort_order'] ?? 0),
                'block_state' => $targetState,
                'old_block_state' => $oldState,
                'new_block' => $old === null,
                'removed_block' => false,
                'changed_block_state' => $oldState !== null && $oldState !== $targetState,
                'locked' => $targetState === BlockState::Locked->value,
                'new_default_content' => $target['default_content'] ?? null,
                'old_content' => $old !== null ? ($oldContentByBlock[$id] ?? null) : null,
                'old_default_content' => $old !== null ? ($old['default_content'] ?? null) : null,
            ];
        }

        foreach ($sourceBlocks as $old) {
            $id = (string) ($old['id'] ?? '');
            if ($id === '' || isset($targetById[$id])) {
                continue;
            }

            $out[] = [
                'template_block_id' => $id,
                'title' => $old['title'] ?? null,
                'type' => $old['type'] ?? 'text',
                'sort_order' => (int) ($old['sort_order'] ?? 0),
                'block_state' => null,
                'old_block_state' => (string) ($old['block_state'] ?? BlockState::Editable->value),
                'new_block' => false,
                'removed_block' => true,
                'changed_block_state' => false,
                'locked' => false,
                'new_default_content' => null,
                'old_content' => $oldContentByBlock[$id] ?? null,
                'old_default_content' => $old['default_content'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $blocks): array
    {
        $indexed = [];
        foreach ($blocks as $block) {
            $id = (string) ($block['id'] ?? '');
            if ($id !== '') {
                $indexed[$id] = $block;
            }
        }

        return $indexed;
    }
}
