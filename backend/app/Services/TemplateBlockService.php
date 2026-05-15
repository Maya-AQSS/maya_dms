<?php

namespace App\Services;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\TemplateBlockDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\TemplateBlockServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\AuditPublisher;

class TemplateBlockService implements TemplateBlockServiceInterface
{
    public function __construct(
        private readonly TemplateBlockRepositoryInterface $blockRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly AuditPublisher $auditPublisher,
    ) {
    }

    /**
     * @return list<TemplateBlockDto>
     */
    public function listForTemplate(string $templateId): array
    {
        // Existencia sin scope de catálogo: la visibilidad la marca el controlador con policy tras cargar.
        $this->templateRepository->findOrFailWithoutCatalogScope($templateId);

        return $this->blockRepository
            ->allForTemplate($templateId)
            ->map(static fn (TemplateBlock $block): TemplateBlockDto => TemplateBlockDto::fromModel($block))
            ->values()
            ->all();
    }

    public function findOrFail(string $id): TemplateBlockDto
    {
        return TemplateBlockDto::fromModel($this->blockRepository->findOrFail($id));
    }

    public function findModelOrFail(string $id): TemplateBlock
    {
        return $this->blockRepository->findOrFail($id);
    }

    /**
     * @param  list<string>  $ids
     * @return list<TemplateBlockDto>
     */
    public function findBlocksByIdsOrFail(array $ids): array
    {
        return $this
            ->findModelsByIdsOrFail($ids)
            ->map(static fn (TemplateBlock $block): TemplateBlockDto => TemplateBlockDto::fromModel($block))
            ->values()
            ->all();
    }

    /**
     * Variante interna que devuelve los Models. Necesario para casos como
     * {@see bulkUpdate()} donde se necesita capturar el estado previo
     * (block_state) antes de la mutación masiva sin pagar la conversión a DTO
     * dos veces.
     *
     * @param  list<string>  $ids
     * @return Collection<int, TemplateBlock>
     */
    private function findModelsByIdsOrFail(array $ids): Collection
    {
        $unique = array_values(array_unique($ids));

        if ($unique === []) {
            throw ValidationException::withMessages([
                'ids' => ['Se requiere al menos un ID de bloque.'],
            ]);
        }

        $blocks = $this->blockRepository->findByIds($unique);

        if ($blocks->count() !== count($unique)) {
            throw ValidationException::withMessages([
                'ids' => ['Uno o más bloques no existen.'],
            ]);
        }

        return $blocks;
    }

    /**
     * @param  list<string>  $orderedBlockIds
     */
    public function reorderForTemplate(string $templateId, array $orderedBlockIds, string $userId): void
    {
        if ($orderedBlockIds === []) {
            throw ValidationException::withMessages([
                'block_ids' => ['Debes enviar al menos un bloque para reordenar.'],
            ]);
        }

        if (count($orderedBlockIds) !== count(array_unique($orderedBlockIds))) {
            throw ValidationException::withMessages([
                'block_ids' => ['La lista de bloques no puede contener IDs duplicados.'],
            ]);
        }

        $this->templateRepository->findOrFailWithoutCatalogScope($templateId);

        $currentBlocks = $this->blockRepository->allForTemplate($templateId);
        $currentIds = $currentBlocks->pluck('id')->map(static fn ($id): string => (string) $id)->all();

        if (count($currentIds) !== count($orderedBlockIds)) {
            throw ValidationException::withMessages([
                'block_ids' => ['Debes enviar todos los bloques de la plantilla.'],
            ]);
        }

        sort($currentIds);
        $incomingIds = $orderedBlockIds;
        sort($incomingIds);

        if ($currentIds !== $incomingIds) {
            throw ValidationException::withMessages([
                'block_ids' => ['La lista enviada no coincide con los bloques reales de la plantilla.'],
            ]);
        }

        $this->blockRepository->reorderForTemplate($templateId, $orderedBlockIds);

        $this->auditPublisher->publish(
            applicationSlug: 'maya-dms',
            entityType:      'template',
            entityId:        $templateId,
            action:          'blocks_reordered',
            userId:          $userId,
            newValue:        ['block_ids' => $orderedBlockIds],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $templateId, array $attributes, string $userId): TemplateBlockDto
    {
        $template = $this->templateRepository->findOrFailWithoutCatalogScope($templateId);
        $block = $this->blockRepository->create($template, $attributes);

        $this->auditPublisher->publish(
            applicationSlug: 'maya-dms',
            entityType:      'template',
            entityId:        $templateId,
            action:          'block_created',
            userId:          $userId,
            blockId:         $block->getKey(),
            newValue:        ['block_state' => $block->block_state],
        );

        return TemplateBlockDto::fromModel($block);
    }

    public function update(string $blockId, UpdateTemplateBlockDto $dto, string $userId): TemplateBlockDto
    {
        $block = $this->blockRepository->findOrFail($blockId);

        $attributes = [];
        if ($dto->set_title) {
            $attributes['title'] = $dto->title;
        }
        if ($dto->set_default_content) {
            $attributes['default_content'] = $dto->default_content;
        }
        if ($dto->set_sort_order) {
            $attributes['sort_order'] = $dto->sort_order;
        }
        if ($dto->set_block_state) {
            $attributes['block_state'] = $dto->block_state;
        }
        if ($dto->set_description) {
            $attributes['description'] = $dto->description;
        }

        if ($attributes === []) {
            return TemplateBlockDto::fromModel($block);
        }

        // Audit only when there is an actual block_state transition.
        $stateOrMandatoryChanged = false;
        if ($dto->set_block_state && $block->block_state !== $dto->block_state) {
            $stateOrMandatoryChanged = true;
        }

        $previous = [
            'block_state' => $block->block_state,
        ];

        $updated = $this->blockRepository->update($block, $attributes);

        if ($stateOrMandatoryChanged) {
            $this->auditPublisher->publish(
                applicationSlug: 'maya-dms',
                entityType:      'template',
                entityId:        $updated->template_id,
                action:          'block_state_changed',
                userId:          $userId,
                blockId:         $blockId,
                previousValue:   $previous,
                newValue:        ['block_state' => $updated->block_state],
            );
        }

        return TemplateBlockDto::fromModel($updated);
    }

    public function delete(string $blockId, string $userId): void
    {
        $block = $this->blockRepository->findOrFail($blockId);

        $this->auditPublisher->publish(
            applicationSlug: 'maya-dms',
            entityType:      'template',
            entityId:        $block->template_id,
            action:          'block_deleted',
            userId:          $userId,
            blockId:         $blockId,
            previousValue:   ['block_state' => $block->block_state],
        );

        $this->blockRepository->delete($block);
    }

    /**
     * @return list<TemplateBlockDto>
     */
    public function bulkUpdate(BulkUpdateTemplateBlocksDto $dto, string $userId): array
    {
        $uniqueIds = array_values(array_unique($dto->ids));

        // Capture previous states before the bulk update to identify actual changes.
        // Uso findModelsByIdsOrFail() porque necesito acceso al block_state previo
        // para detectar transición real antes de auditar.
        $before = $this->findModelsByIdsOrFail($uniqueIds)->keyBy('id');

        $attributes = [];
        if ($dto->set_block_state) {
            $attributes['block_state'] = $dto->block_state;
        }

        $updated = $this->blockRepository->bulkUpdate($dto->ids, $attributes);

        foreach ($updated as $block) {
            $prev = $before->get($block->getKey());

            // Redundant audit prevention: only log if something actually changed
            $changedState = $dto->set_block_state && $prev && $prev->block_state !== $block->block_state;

            if ($changedState) {
                $this->auditPublisher->publish(
                    applicationSlug: 'maya-dms',
                    entityType:      'template',
                    entityId:        $block->template_id,
                    action:          'block_state_changed',
                    userId:          $userId,
                    blockId:         $block->getKey(),
                    previousValue:   ['block_state' => $prev->block_state],
                    newValue:        ['block_state' => $block->block_state],
                );
            }
        }

        return $updated
            ->map(static fn (TemplateBlock $block): TemplateBlockDto => TemplateBlockDto::fromModel($block))
            ->values()
            ->all();
    }
}
