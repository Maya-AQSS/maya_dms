<?php

namespace App\Services\Contracts;

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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $blockId, array $attributes, string $userId): TemplateBlock;

    public function delete(string $blockId, string $userId): void;

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(array $ids, array $attributes, string $userId): Collection;
}
