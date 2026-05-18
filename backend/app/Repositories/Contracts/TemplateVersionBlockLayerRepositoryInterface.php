<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TemplateVersionBlockLayer;
use Illuminate\Support\Collection;

interface TemplateVersionBlockLayerRepositoryInterface
{
    /**
     * @return Collection<int, TemplateVersionBlockLayer>
     */
    public function listForVersion(string $entityVersionId): Collection;

    public function findForVersionAndBlock(string $entityVersionId, string $templateBlockId): ?TemplateVersionBlockLayer;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TemplateVersionBlockLayer;
}
