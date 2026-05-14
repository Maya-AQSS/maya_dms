<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DocumentVersionBlockLayer;
use Illuminate\Support\Collection;

interface DocumentVersionBlockLayerRepositoryInterface
{
    /**
     * @return Collection<int, DocumentVersionBlockLayer>
     */
    public function listForVersion(string $documentVersionId): Collection;

    public function findForVersionAndBlock(string $documentVersionId, string $documentBlockId): ?DocumentVersionBlockLayer;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DocumentVersionBlockLayer;
}
