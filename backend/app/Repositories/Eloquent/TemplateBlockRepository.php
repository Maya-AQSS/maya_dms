<?php

namespace App\Repositories\Eloquent;

use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateBlockRepository implements TemplateBlockRepositoryInterface
{
    /**
     * @return Collection<int, TemplateBlock>
     */
    public function allForTemplate(string $templateId): Collection
    {
        return TemplateBlock::where('template_id', $templateId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    public function findOrFail(string $id): TemplateBlock
    {
        return TemplateBlock::findOrFail($id);
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, TemplateBlock>
     */
    public function findByIds(array $ids): Collection
    {
        return TemplateBlock::whereIn('id', $ids)->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Template $template, array $attributes): TemplateBlock
    {
        $maxOrder = TemplateBlock::where('template_id', $template->getKey())
            ->max('sort_order') ?? -1;

        return TemplateBlock::forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $template->getKey(),
            'type'            => $attributes['type'],
            'title'           => $attributes['title'] ?? null,
            'default_content' => $attributes['default_content'] ?? null,
            'block_state'     => $attributes['block_state'] ?? 'editable',
            'mandatory'       => $attributes['mandatory'] ?? false,
            'sort_order'      => $attributes['sort_order'] ?? ($maxOrder + 1),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TemplateBlock $block, array $attributes): TemplateBlock
    {
        if ($attributes !== []) {
            $block->update($attributes);
        }

        return $block->fresh();
    }

    public function delete(TemplateBlock $block): void
    {
        $block->delete();
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(array $ids, array $attributes): Collection
    {
        if ($ids === [] || $attributes === []) {
            return TemplateBlock::whereIn('id', $ids)->get();
        }

        DB::transaction(function () use ($ids, $attributes) {
            TemplateBlock::whereIn('id', $ids)->update($attributes);
        });

        return TemplateBlock::whereIn('id', $ids)->get();
    }
}
