<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DocumentVersionBlockLayer;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use Illuminate\Support\Collection;

class DocumentVersionBlockLayerRepository implements DocumentVersionBlockLayerRepositoryInterface
{
    public function listForVersion(string $documentVersionId): Collection
    {
        return DocumentVersionBlockLayer::query()
            ->where('document_version_id', $documentVersionId)
            ->orderBy('sort_order')
            ->orderBy('document_block_id')
            ->get();
    }

    public function findForVersionAndBlock(string $documentVersionId, string $documentBlockId): ?DocumentVersionBlockLayer
    {
        return DocumentVersionBlockLayer::query()
            ->where('document_version_id', $documentVersionId)
            ->where('document_block_id', $documentBlockId)
            ->first();
    }

    public function create(array $attributes): DocumentVersionBlockLayer
    {
        return DocumentVersionBlockLayer::query()->create($attributes);
    }
}
