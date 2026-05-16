<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DocumentBlock;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use Illuminate\Support\Collection;

class DocumentBlockRepository implements DocumentBlockRepositoryInterface
{
    public function findInDocumentOrFail(string $blockId, string $documentId): DocumentBlock
    {
        return DocumentBlock::query()
            ->with('templateBlock')
            ->where('id', $blockId)
            ->where('document_id', $documentId)
            ->firstOrFail();
    }

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

    public function findTemplateBlockIdsInUseByTemplate(string $templateId): array
    {
        return DocumentBlock::query()
            ->whereIn('template_block_id', function ($query) use ($templateId): void {
                $query->select('id')
                    ->from((new TemplateBlock())->getTable())
                    ->where('template_id', $templateId);
            })
            ->pluck('template_block_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }
}
