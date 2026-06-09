<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Templates\TemplateVersionBlockLayerDto;
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

    /**
     * @return Collection<int, TemplateVersionBlockLayerDto>
     */
    public function listForVersionAsDto(string $entityVersionId): Collection
    {
        return $this->listForVersion($entityVersionId)
            ->map(fn (TemplateVersionBlockLayer $layer) => new TemplateVersionBlockLayerDto(
                id: (string) $layer->id,
                entityVersionId: (string) $layer->entity_version_id,
                templateBlockId: (string) $layer->template_block_id,
                removed: (bool) $layer->removed,
                overridePayload: is_array($layer->override_payload) ? $layer->override_payload : null,
                inheritsFromPreviousPublication: (bool) $layer->inherits_from_previous_publication,
                sortOrder: (int) $layer->sort_order,
            ));
    }

    public function findForVersionAndBlock(string $entityVersionId, string $templateBlockId): ?TemplateVersionBlockLayer
    {
        return TemplateVersionBlockLayer::query()
            ->where('entity_version_id', $entityVersionId)
            ->where('template_block_id', $templateBlockId)
            ->first();
    }

    public function findForVersionAndBlockAsDto(string $entityVersionId, string $templateBlockId): ?TemplateVersionBlockLayerDto
    {
        $layer = $this->findForVersionAndBlock($entityVersionId, $templateBlockId);
        if ($layer === null) {
            return null;
        }

        return new TemplateVersionBlockLayerDto(
            id: (string) $layer->id,
            entityVersionId: (string) $layer->entity_version_id,
            templateBlockId: (string) $layer->template_block_id,
            removed: (bool) $layer->removed,
            overridePayload: is_array($layer->override_payload) ? $layer->override_payload : null,
            inheritsFromPreviousPublication: (bool) $layer->inherits_from_previous_publication,
            sortOrder: (int) $layer->sort_order,
        );
    }

    public function create(array $attributes): TemplateVersionBlockLayer
    {
        return TemplateVersionBlockLayer::query()->create($attributes);
    }
}
