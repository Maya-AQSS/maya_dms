<?php

namespace App\Services;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\TemplateBlockServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class TemplateBlockService implements TemplateBlockServiceInterface
{
    public function __construct(
        private readonly TemplateBlockRepositoryInterface $blockRepository,
        private readonly TemplateRepositoryInterface      $templateRepository,
        private readonly AuditLogServiceInterface         $auditLogService,
    ) {}

    /**
     * @return Collection<int, TemplateBlock>
     */
    public function listForTemplate(string $templateId): Collection
    {
        // Validate the template exists (also applies global scopes / visibility)
        $this->templateRepository->findOrFail($templateId);

        return $this->blockRepository->allForTemplate($templateId);
    }

    public function findOrFail(string $id): TemplateBlock
    {
        return $this->blockRepository->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $templateId, array $attributes, string $userId): TemplateBlock
    {
        $template = $this->templateRepository->findOrFail($templateId);
        $block    = $this->blockRepository->create($template, $attributes);

        $this->auditLogService->record(
            entityType:    'template',
            entityId:      $templateId,
            action:        'block_created',
            userId:        $userId,
            blockId:       $block->getKey(),
            previousValue: null,
            newValue:      [
                'block_state' => $block->block_state,
                'mandatory'   => $block->mandatory,
                'type'        => $block->type,
            ],
        );

        return $block;
    }

    public function update(string $blockId, UpdateTemplateBlockDto $dto, string $userId): TemplateBlock
    {
        $block = $this->blockRepository->findOrFail($blockId);

        $attributes = [];
        if ($dto->set_type) {
            $attributes['type'] = $dto->type;
        }
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
        if ($dto->set_mandatory) {
            $attributes['mandatory'] = $dto->mandatory;
        }
        if ($dto->set_description) {
            $attributes['description'] = $dto->description;
        }

        if ($attributes === []) {
            return $block;
        }

        // Audit only when there is an actual state/mandatory transition.
        $stateOrMandatoryChanged = false;
        if ($dto->set_block_state && $block->block_state !== $dto->block_state) {
            $stateOrMandatoryChanged = true;
        }
        if ($dto->set_mandatory && $block->mandatory !== $dto->mandatory) {
            $stateOrMandatoryChanged = true;
        }

        $previous = [
            'block_state' => $block->block_state,
            'mandatory'   => $block->mandatory,
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
                    'mandatory'   => $updated->mandatory,
                ],
            );
        }

        return $updated;
    }

    public function delete(string $blockId, string $userId): void
    {
        $block = $this->blockRepository->findOrFail($blockId);

        $this->auditLogService->record(
            entityType:    'template',
            entityId:      $block->template_id,
            action:        'block_deleted',
            userId:        $userId,
            blockId:       $blockId,
            previousValue: [
                'block_state' => $block->block_state,
                'mandatory'   => $block->mandatory,
                'type'        => $block->type,
            ],
            newValue: null,
        );

        $this->blockRepository->delete($block);
    }

    public function bulkUpdate(BulkUpdateTemplateBlocksDto $dto, string $userId): Collection
    {
        // Capture previous states before the bulk update to identify actual changes
        $before = $this->blockRepository->findByIds($dto->ids)->keyBy('id');

        $attributes = [];
        if ($dto->set_block_state) {
            $attributes['block_state'] = $dto->block_state;
        }
        if ($dto->set_mandatory) {
            $attributes['mandatory'] = $dto->mandatory;
        }

        $updated = $this->blockRepository->bulkUpdate($dto->ids, $attributes);

        foreach ($updated as $block) {
            $prev = $before->get($block->getKey());

            // Redundant audit prevention: only log if something actually changed
            $changedState = $dto->set_block_state && $prev && $prev->block_state !== $block->block_state;
            $changedMandatory = $dto->set_mandatory && $prev && $prev->mandatory !== $block->mandatory;

            if ($changedState || $changedMandatory) {
                $this->auditLogService->record(
                    entityType:    'template',
                    entityId:      $block->template_id,
                    action:        'block_state_changed',
                    userId:        $userId,
                    blockId:       $block->getKey(),
                    previousValue: [
                        'block_state' => $prev->block_state,
                        'mandatory'   => $prev->mandatory,
                    ],
                    newValue: [
                        'block_state' => $block->block_state,
                        'mandatory'   => $block->mandatory,
                    ],
                );
            }
        }

        return $updated;
    }
}
