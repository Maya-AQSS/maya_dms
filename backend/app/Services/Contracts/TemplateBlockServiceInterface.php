<?php

namespace App\Services\Contracts;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Models\TemplateBlock;
use Illuminate\Database\Eloquent\Collection;

interface TemplateBlockServiceInterface
{
    /**
     * @return Collection<int, TemplateBlock>
     */
    public function listForTemplate(string $templateId): Collection;

    public function findOrFail(string $id): TemplateBlock;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $templateId, array $attributes, string $userId): TemplateBlock;

    public function update(string $blockId, UpdateTemplateBlockDto $dto, string $userId): TemplateBlock;

    public function delete(string $blockId, string $userId): void;

    /**
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(BulkUpdateTemplateBlocksDto $dto, string $userId): Collection;
}
