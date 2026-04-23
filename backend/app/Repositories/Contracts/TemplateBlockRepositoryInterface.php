<?php

namespace App\Repositories\Contracts;

use App\Models\Template;
use App\Models\TemplateBlock;
use Illuminate\Database\Eloquent\Collection;

interface TemplateBlockRepositoryInterface
{
    /**
     * @return Collection<int, TemplateBlock>
     */
    public function allForTemplate(string $templateId): Collection;

    public function findOrFail(string $id): TemplateBlock;

    /**
     * @param  list<string>  $ids
     * @return Collection<int, TemplateBlock>
     */
    public function findByIds(array $ids): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Template $template, array $attributes): TemplateBlock;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TemplateBlock $block, array $attributes): TemplateBlock;

    public function delete(TemplateBlock $block): void;

    /**
     * Actualiza block_state en múltiples bloques.
     * Devuelve los bloques actualizados en el mismo orden que $ids.
     *
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(array $ids, array $attributes): Collection;
}
