<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Versioning\VersionBlockLayerDto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Abstract base for version block layer repositories.
 *
 * Concrete subclasses configure three properties that vary between domains:
 *   - $modelClass       — the Eloquent model class
 *   - $versionFkColumn  — FK column pointing to the version row
 *   - $blockFkColumn    — FK column pointing to the block row
 *
 * All SQL is identical across domains; only the column names differ.
 */
abstract class AbstractVersionBlockLayerRepository
{
    /** Fully-qualified Eloquent model class name. Initialized by each subclass. */
    protected string $modelClass;

    /** Column name of the FK referencing the version (e.g. entity_version_id). */
    protected string $versionFkColumn;

    /** Column name of the FK referencing the block (e.g. template_block_id). */
    protected string $blockFkColumn;

    // ─── Shared implementations ───────────────────────────────────────────────

    /**
     * @return Collection<int, Model>
     */
    protected function baseListForVersion(string $versionId): Collection
    {
        return $this->modelClass::query()
            ->where($this->versionFkColumn, $versionId)
            ->orderBy('sort_order')
            ->orderBy($this->blockFkColumn)
            ->get();
    }

    /**
     * @return Collection<int, VersionBlockLayerDto>
     */
    protected function baseListForVersionAsDto(string $versionId): Collection
    {
        return $this->baseListForVersion($versionId)
            ->map(fn (Model $layer) => $this->toDto($layer));
    }

    protected function baseFindForVersionAndBlock(string $versionId, string $blockId): ?Model
    {
        return $this->modelClass::query()
            ->where($this->versionFkColumn, $versionId)
            ->where($this->blockFkColumn, $blockId)
            ->first();
    }

    protected function baseFindForVersionAndBlockAsDto(string $versionId, string $blockId): ?VersionBlockLayerDto
    {
        $layer = $this->baseFindForVersionAndBlock($versionId, $blockId);
        if ($layer === null) {
            return null;
        }

        return $this->toDto($layer);
    }

    protected function baseCreate(array $attributes): Model
    {
        return $this->modelClass::query()->create($attributes);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function toDto(Model $layer): VersionBlockLayerDto
    {
        return new VersionBlockLayerDto(
            id: (string) $layer->id,
            versionId: (string) $layer->{$this->versionFkColumn},
            blockId: (string) $layer->{$this->blockFkColumn},
            removed: (bool) $layer->removed,
            overridePayload: is_array($layer->override_payload) ? $layer->override_payload : null,
            inheritsFromPreviousPublication: (bool) $layer->inherits_from_previous_publication,
            sortOrder: (int) $layer->sort_order,
        );
    }
}
