<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\TemplateBlockDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Events\TemplateBlockCreated;
use App\Events\TemplateBlockDeleted;
use App\Events\TemplateBlocksReordered;
use App\Events\TemplateBlockStateChanged;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\TemplateBlockServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TemplateBlockService implements TemplateBlockServiceInterface
{
    public function __construct(
        private readonly TemplateBlockRepositoryInterface $blockRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

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

    /**
     * @internal For authorization checks in controllers only. Do not use in other services.
     */
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

        TemplateBlocksReordered::dispatch($templateId, $orderedBlockIds, $userId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $templateId, array $attributes, string $userId): TemplateBlockDto
    {
        $template = $this->templateRepository->findOrFailWithoutCatalogScope($templateId);
        $block = $this->blockRepository->create($template, $attributes);

        TemplateBlockCreated::dispatch($templateId, $block, $userId);

        return TemplateBlockDto::fromModel($block);
    }

    public function update(string $blockId, UpdateTemplateBlockDto $dto, string $userId): TemplateBlockDto
    {
        $block = $this->blockRepository->findOrFail($blockId);

        $attributes = [];
        if ($dto->setTitle) {
            $attributes['title'] = $dto->title;
        }
        if ($dto->setDefaultContent) {
            $attributes['default_content'] = $dto->defaultContent;
        }
        if ($dto->setSortOrder) {
            $attributes['sort_order'] = $dto->sortOrder;
        }
        if ($dto->setBlockState) {
            $attributes['block_state'] = $dto->blockState;
        }
        if ($dto->setDescription) {
            $attributes['description'] = $dto->description;
        }
        if ($dto->setBlockType) {
            $attributes['block_type'] = $dto->blockType;
        }
        if ($dto->setPageBreakAfter) {
            $attributes['page_break_after'] = $dto->pageBreakAfter;
        }
        if ($dto->setThemeId) {
            $attributes['theme_id'] = $dto->themeId;
        }
        if ($dto->setApplyTheme) {
            $attributes['apply_theme'] = $dto->applyTheme;
        }

        if ($attributes === []) {
            return TemplateBlockDto::fromModel($block);
        }

        // Audit only when there is an actual block_state transition.
        $stateOrMandatoryChanged = false;
        if ($dto->setBlockState && $block->block_state !== $dto->blockState) {
            $stateOrMandatoryChanged = true;
        }

        $previous = [
            'block_state' => $block->block_state,
        ];

        $updated = $this->blockRepository->update($block, $attributes);

        if ($stateOrMandatoryChanged) {
            TemplateBlockStateChanged::dispatch(
                (string) $updated->template_id,
                $blockId,
                (string) $previous['block_state'],
                (string) $updated->block_state,
                $userId,
            );
        }

        return TemplateBlockDto::fromModel($updated);
    }

    public function delete(string $blockId, string $userId): void
    {
        $block = $this->blockRepository->findOrFail($blockId);

        TemplateBlockDeleted::dispatch(
            (string) $block->template_id,
            $blockId,
            (string) $block->block_state,
            $userId,
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
        if ($dto->setBlockState) {
            $attributes['block_state'] = $dto->blockState;
        }

        $updated = $this->blockRepository->bulkUpdate($dto->ids, $attributes);

        foreach ($updated as $block) {
            $prev = $before->get($block->getKey());

            // Redundant audit prevention: only log if something actually changed
            $changedState = $dto->setBlockState && $prev && $prev->block_state !== $block->block_state;

            if ($changedState) {
                TemplateBlockStateChanged::dispatch(
                    (string) $block->template_id,
                    (string) $block->getKey(),
                    (string) $prev->block_state,
                    (string) $block->block_state,
                    $userId,
                );
            }
        }

        return $updated
            ->map(static fn (TemplateBlock $block): TemplateBlockDto => TemplateBlockDto::fromModel($block))
            ->values()
            ->all();
    }
}
