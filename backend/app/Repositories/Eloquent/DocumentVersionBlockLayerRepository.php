<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Documents\DocumentVersionBlockLayerDto;
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

    /**
     * @return Collection<int, DocumentVersionBlockLayerDto>
     */
    public function listForVersionAsDto(string $documentVersionId): Collection
    {
        return $this->listForVersion($documentVersionId)
            ->map(fn (DocumentVersionBlockLayer $layer) => new DocumentVersionBlockLayerDto(
                id: (string) $layer->id,
                documentVersionId: (string) $layer->document_version_id,
                documentBlockId: (string) $layer->document_block_id,
                removed: (bool) $layer->removed,
                overridePayload: is_array($layer->override_payload) ? $layer->override_payload : null,
                inheritsFromPreviousPublication: (bool) $layer->inherits_from_previous_publication,
                sortOrder: (int) $layer->sort_order,
            ));
    }

    public function findForVersionAndBlock(string $documentVersionId, string $documentBlockId): ?DocumentVersionBlockLayer
    {
        return DocumentVersionBlockLayer::query()
            ->where('document_version_id', $documentVersionId)
            ->where('document_block_id', $documentBlockId)
            ->first();
    }

    /**
     * @return DocumentVersionBlockLayerDto|null
     */
    public function findForVersionAndBlockAsDto(string $documentVersionId, string $documentBlockId): ?DocumentVersionBlockLayerDto
    {
        $layer = $this->findForVersionAndBlock($documentVersionId, $documentBlockId);
        if ($layer === null) {
            return null;
        }

        return new DocumentVersionBlockLayerDto(
            id: (string) $layer->id,
            documentVersionId: (string) $layer->document_version_id,
            documentBlockId: (string) $layer->document_block_id,
            removed: (bool) $layer->removed,
            overridePayload: is_array($layer->override_payload) ? $layer->override_payload : null,
            inheritsFromPreviousPublication: (bool) $layer->inherits_from_previous_publication,
            sortOrder: (int) $layer->sort_order,
        );
    }

    public function create(array $attributes): DocumentVersionBlockLayer
    {
        return DocumentVersionBlockLayer::query()->create($attributes);
    }
}
