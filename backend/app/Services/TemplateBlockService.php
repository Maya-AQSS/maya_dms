<?php

namespace App\Services;

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
            blockUuid:     $block->getKey(),
            previousValue: null,
            newValue:      [
                'block_state' => $block->block_state,
                'mandatory'   => $block->mandatory,
                'type'        => $block->type,
            ],
        );

        return $block;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $blockId, array $attributes, string $userId): TemplateBlock
    {
        $block    = $this->blockRepository->findOrFail($blockId);
        $previous = [
            'block_state' => $block->block_state,
            'mandatory'   => $block->mandatory,
        ];

        $updated = $this->blockRepository->update($block, $attributes);

        $this->auditLogService->record(
            entityType:    'template',
            entityId:      $updated->template_id,
            action:        'block_state_changed',
            userId:        $userId,
            blockUuid:     $blockId,
            previousValue: $previous,
            newValue:      [
                'block_state' => $updated->block_state,
                'mandatory'   => $updated->mandatory,
            ],
        );

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
            blockUuid:     $blockId,
            previousValue: [
                'block_state' => $block->block_state,
                'mandatory'   => $block->mandatory,
                'type'        => $block->type,
            ],
            newValue: null,
        );

        $this->blockRepository->delete($block);
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(array $ids, array $attributes, string $userId): Collection
    {
        // Capture previous states before the bulk update
        $before = $this->blockRepository->findByIds($ids)->keyBy('id');

        $updated = $this->blockRepository->bulkUpdate($ids, $attributes);

        foreach ($updated as $block) {
            $prev = $before->get($block->getKey());

            $this->auditLogService->record(
                entityType:    'template',
                entityId:      $block->template_id,
                action:        'block_state_changed',
                userId:        $userId,
                blockUuid:     $block->getKey(),
                previousValue: $prev ? [
                    'block_state' => $prev->block_state,
                    'mandatory'   => $prev->mandatory,
                ] : null,
                newValue: [
                    'block_state' => $block->block_state,
                    'mandatory'   => $block->mandatory,
                ],
            );
        }

        return $updated;
    }
}
