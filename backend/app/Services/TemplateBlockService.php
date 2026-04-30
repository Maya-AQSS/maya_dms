<?php

namespace App\Services;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\TemplateBlockServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TemplateBlockService implements TemplateBlockServiceInterface
{
    public function __construct(
        private readonly TemplateBlockRepositoryInterface $blockRepository,
        private readonly TemplateRepositoryInterface      $templateRepository,
        private readonly AuditLogServiceInterface         $auditLogService,
    ) {}

    /**
     * Lista todos los bloques de una plantilla.
     * 
     * @return Collection<int, TemplateBlock>
     */
    public function listForTemplate(string $templateId): Collection
    {
        // Validate the template exists (also applies global scopes / visibility)
        $this->templateRepository->findOrFail($templateId);

        return $this->blockRepository->allForTemplate($templateId);
    }

    /**
     * Busca un bloque por ID. Lanza excepción si no existe.
     * 
     * @param  string  $id
     * @return TemplateBlock
     */
    public function findOrFail(string $id): TemplateBlock
    {
        return $this->blockRepository->findOrFail($id);
    }

    /**
     * Carga todos los bloques por ID (IDs únicos). Lanza validación si falta alguno.
     *
     * @param  list<string>  $ids
     * @return Collection<int, TemplateBlock>
     */
    public function findBlocksByIdsOrFail(array $ids): Collection
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
    public function reorderForTemplate(string $templateId, array $orderedBlockIds): void
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

        $template = $this->templateRepository->findOrFail($templateId);
        $this->assertUserMayUpdateTemplate($template);

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
    }

    /**
     * Crea un nuevo bloque para una plantilla.
     * 
     * @param  string  $templateId
     * @param  string  $userId
     * @param  array<string, mixed>  $attributes
     * @return TemplateBlock
     */
    public function create(string $templateId, array $attributes, string $userId): TemplateBlock
    {
        $template = $this->templateRepository->findOrFail($templateId);
        $this->assertUserMayUpdateTemplate($template);
        $block = $this->blockRepository->create($template, $attributes);

        $this->auditLogService->record(
            entityType:    'template',
            entityId:      $templateId,
            action:        'block_created',
            userId:        $userId,
            blockId:       $block->getKey(),
            previousValue: null,
            newValue:      [
                'block_state' => $block->block_state,
            ],
        );

        return $block;
    }

    /**
     * Actualiza un bloque existente de una plantilla.
     * 
     * @param  string  $blockId
     * @param  UpdateTemplateBlockDto  $dto
     * @param  string  $userId
     * @return TemplateBlock
     */
    public function update(string $blockId, UpdateTemplateBlockDto $dto, string $userId): TemplateBlock
    {
        $block = $this->blockRepository->findOrFail($blockId);
        $this->assertUserMayUpdateTemplate(
            $this->templateRepository->findOrFail($block->template_id),
        );

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
            return $block;
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
            $this->auditLogService->record(
                entityType:    'template',
                entityId:      $updated->template_id,
                action:        'block_state_changed',
                userId:        $userId,
                blockId:       $blockId,
                previousValue: $previous,
                newValue:      [
                    'block_state' => $updated->block_state,
                ],
            );
        }

        return $updated;
    }

    /**
     * Elimina un bloque de una plantilla.
     * 
     * @param  string  $blockId
     * @param  string  $userId
     * @return void
     */
    public function delete(string $blockId, string $userId): void
    {
        $block = $this->blockRepository->findOrFail($blockId);
        $this->assertUserMayUpdateTemplate(
            $this->templateRepository->findOrFail($block->template_id),
        );

        $this->auditLogService->record(
            entityType:    'template',
            entityId:      $block->template_id,
            action:        'block_deleted',
            userId:        $userId,
            blockId:       $blockId,
            previousValue: [
                'block_state' => $block->block_state,
            ],
            newValue: null,
        );

        $this->blockRepository->delete($block);
    }

    /**
     * Actualiza múltiples bloques de una plantilla.
     * 
     * @param  BulkUpdateTemplateBlocksDto  $dto
     * @param  string  $userId
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(BulkUpdateTemplateBlocksDto $dto, string $userId): Collection
    {
        $uniqueIds = array_values(array_unique($dto->ids));

        // Capture previous states before the bulk update to identify actual changes
        $before = $this->findBlocksByIdsOrFail($uniqueIds)->keyBy('id');

        foreach ($before->pluck('template_id')->unique() as $templateId) {
            $this->assertUserMayUpdateTemplate(
                $this->templateRepository->findOrFail((string) $templateId),
            );
        }

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
                $this->auditLogService->record(
                    entityType:    'template',
                    entityId:      $block->template_id,
                    action:        'block_state_changed',
                    userId:        $userId,
                    blockId:       $block->getKey(),
                    previousValue: [
                        'block_state' => $prev->block_state,
                        ],
                    newValue: [
                        'block_state' => $block->block_state,
                            ],
                );
            }
        }

        return $updated;
    }

    /**
     * Verifica si el usuario tiene permiso para actualizar una plantilla.
     * 
     * @param  Template  $template
     * @return void
     */
    private function assertUserMayUpdateTemplate(Template $template): void
    {
        Gate::forUser(Auth::user())->authorize('update', $template);
    }
}
