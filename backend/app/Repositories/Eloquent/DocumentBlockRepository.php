<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DocumentBlock;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use Illuminate\Support\Collection;

class DocumentBlockRepository implements DocumentBlockRepositoryInterface
{
    public function listByDocumentKeyedByTemplateBlock(string $documentId): Collection
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->get()
            ->keyBy('template_block_id');
    }

    public function create(array $attributes): DocumentBlock
    {
        return DocumentBlock::query()->create($attributes);
    }

    public function deleteAllForDocument(string $documentId): int
    {
        return DocumentBlock::query()->where('document_id', $documentId)->delete();
    }

    public function deleteForDocumentExceptTemplateBlocks(string $documentId, array $keepTemplateBlockIds): int
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->whereNotIn('template_block_id', $keepTemplateBlockIds)
            ->delete();
    }
}
