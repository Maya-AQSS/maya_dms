<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\TemplateVersionBlockLayer;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use Illuminate\Support\Collection;

class TemplateVersionBlockLayerRepository implements TemplateVersionBlockLayerRepositoryInterface
{
    public function listForVersion(string $entityVersionId): Collection
    {
        return TemplateVersionBlockLayer::query()
            ->where('entity_version_id', $entityVersionId)
            ->orderBy('sort_order')
            ->orderBy('template_block_id')
            ->get();
    }

    public function findForVersionAndBlock(string $entityVersionId, string $templateBlockId): ?TemplateVersionBlockLayer
    {
        return TemplateVersionBlockLayer::query()
            ->where('entity_version_id', $entityVersionId)
            ->where('template_block_id', $templateBlockId)
            ->first();
    }

    public function create(array $attributes): TemplateVersionBlockLayer
    {
        return TemplateVersionBlockLayer::query()->create($attributes);
    }
}
