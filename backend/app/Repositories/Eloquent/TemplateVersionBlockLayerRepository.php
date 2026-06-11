<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Versioning\VersionBlockLayerDto;
use App\Models\TemplateVersionBlockLayer;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use Illuminate\Support\Collection;

class TemplateVersionBlockLayerRepository extends AbstractVersionBlockLayerRepository implements TemplateVersionBlockLayerRepositoryInterface
{
    protected string $modelClass = TemplateVersionBlockLayer::class;

    protected string $versionFkColumn = 'entity_version_id';

    protected string $blockFkColumn = 'template_block_id';

    public function listForVersion(string $entityVersionId): Collection
    {
        return $this->baseListForVersion($entityVersionId);
    }

    /**
     * @return Collection<int, VersionBlockLayerDto>
     */
    public function listForVersionAsDto(string $entityVersionId): Collection
    {
        return $this->baseListForVersionAsDto($entityVersionId);
    }

    public function findForVersionAndBlock(string $entityVersionId, string $templateBlockId): ?TemplateVersionBlockLayer
    {
        /** @var TemplateVersionBlockLayer|null */
        return $this->baseFindForVersionAndBlock($entityVersionId, $templateBlockId);
    }

    public function findForVersionAndBlockAsDto(string $entityVersionId, string $templateBlockId): ?VersionBlockLayerDto
    {
        return $this->baseFindForVersionAndBlockAsDto($entityVersionId, $templateBlockId);
    }

    public function create(array $attributes): TemplateVersionBlockLayer
    {
        /** @var TemplateVersionBlockLayer */
        return $this->baseCreate($attributes);
    }
}
