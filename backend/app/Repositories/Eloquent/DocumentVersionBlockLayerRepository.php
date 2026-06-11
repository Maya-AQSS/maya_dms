<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Versioning\VersionBlockLayerDto;
use App\Models\DocumentVersionBlockLayer;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use Illuminate\Support\Collection;

class DocumentVersionBlockLayerRepository extends AbstractVersionBlockLayerRepository implements DocumentVersionBlockLayerRepositoryInterface
{
    protected string $modelClass = DocumentVersionBlockLayer::class;

    protected string $versionFkColumn = 'document_version_id';

    protected string $blockFkColumn = 'document_block_id';

    public function listForVersion(string $documentVersionId): Collection
    {
        return $this->baseListForVersion($documentVersionId);
    }

    /**
     * @return Collection<int, VersionBlockLayerDto>
     */
    public function listForVersionAsDto(string $documentVersionId): Collection
    {
        return $this->baseListForVersionAsDto($documentVersionId);
    }

    public function findForVersionAndBlock(string $documentVersionId, string $documentBlockId): ?DocumentVersionBlockLayer
    {
        /** @var DocumentVersionBlockLayer|null */
        return $this->baseFindForVersionAndBlock($documentVersionId, $documentBlockId);
    }

    public function findForVersionAndBlockAsDto(string $documentVersionId, string $documentBlockId): ?VersionBlockLayerDto
    {
        return $this->baseFindForVersionAndBlockAsDto($documentVersionId, $documentBlockId);
    }

    public function create(array $attributes): DocumentVersionBlockLayer
    {
        /** @var DocumentVersionBlockLayer */
        return $this->baseCreate($attributes);
    }
}
