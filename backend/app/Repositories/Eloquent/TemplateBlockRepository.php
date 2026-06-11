<?php

declare(strict_types=1);

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
            'id' => (string) Str::uuid(),
            'template_id' => $template->getKey(),
            'block_type' => $attributes['block_type'] ?? 'content',
            'theme_id' => $attributes['theme_id'] ?? null,
            'apply_theme' => $attributes['apply_theme'] ?? true,
            'title' => $attributes['title'] ?? null,
            'default_content' => $attributes['default_content'] ?? null,
            'description' => $attributes['description'] ?? null,
            'block_state' => $attributes['block_state'] ?? 'editable',
            'page_break_after' => $attributes['page_break_after'] ?? false,
            'page_number_start' => $attributes['page_number_start'] ?? false,
            'sort_order' => $attributes['sort_order'] ?? ($maxOrder + 1),
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

    public function upsertByIdForTemplate(string $blockId, array $values): void
    {
        $updated = TemplateBlock::query()->whereKey($blockId)->update($values);
        if ($updated === 0) {
            TemplateBlock::query()->forceCreate([
                'id' => $blockId,
                ...$values,
            ]);
        }
    }

    public function deleteForTemplateExcept(string $templateId, array $protectedIds): int
    {
        $query = TemplateBlock::query()->where('template_id', $templateId);
        if ($protectedIds !== []) {
            $query->whereNotIn('id', $protectedIds);
        }

        return $query->delete();
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

        $blocks = TemplateBlock::whereIn('id', $ids)->get();

        // Reorder to preserve contract: result in same order as $ids.
        $position = array_flip($ids);

        return $blocks->sortBy(fn ($b) => $position[(string) $b->getKey()] ?? PHP_INT_MAX)->values();
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorderForTemplate(string $templateId, array $orderedIds): void
    {
        DB::transaction(function () use ($templateId, $orderedIds): void {
            foreach ($orderedIds as $index => $blockId) {
                TemplateBlock::query()
                    ->where('id', $blockId)
                    ->where('template_id', $templateId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
