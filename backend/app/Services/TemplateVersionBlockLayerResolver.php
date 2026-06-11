<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Versioning\EntityVersionSnapshotDto;
use App\DTOs\Versioning\VersionBlockLayerDto;
use App\Enums\BlockType;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Services\Concerns\AbstractBlockLayerResolver;
use Illuminate\Support\Collection;

/**
 * Reconstruye el snapshot efectivo de bloques desde capas incrementales.
 * Accepts scalar IDs and DTOs, not Eloquent models.
 *
 * @extends AbstractBlockLayerResolver<EntityVersionSnapshotDto, VersionBlockLayerDto>
 */
final class TemplateVersionBlockLayerResolver extends AbstractBlockLayerResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateVersionBlockLayerRepositoryInterface $layerRepository,
        private readonly TemplateBlockRepositoryInterface $templateBlockRepository,
    ) {}

    // ─── Domain-specific snapshot accessors ───────────────────────────────────

    protected function loadSnapshotByVersionId(string $versionId): EntityVersionSnapshotDto
    {
        return $this->entityVersionRepository->findOrFailAsSnapshot($versionId);
    }

    protected function loadParentSnapshot(mixed $snapshotDto): ?EntityVersionSnapshotDto
    {
        return $this->entityVersionRepository->findOrFailPublishedByEntityAndNumberAsSnapshot(
            Template::class,
            $snapshotDto->entityId,
            $snapshotDto->versionNumber - 1,
        );
    }

    protected function loadLayersForVersion(string $versionId): Collection
    {
        return $this->layerRepository->listForVersionAsDto($versionId);
    }

    protected function loadLayerForVersionAndBlock(string $versionId, string $blockId): ?VersionBlockLayerDto
    {
        return $this->layerRepository->findForVersionAndBlockAsDto($versionId, $blockId);
    }

    protected function snapshotBlockRows(mixed $snapshotDto): array
    {
        return array_values($snapshotDto->blocksSnapshotRows);
    }

    protected function snapshotId(mixed $snapshotDto): string
    {
        return $snapshotDto->id;
    }

    protected function snapshotVersionNumber(mixed $snapshotDto): int
    {
        return $snapshotDto->versionNumber;
    }

    protected function layerBlockId(mixed $layerDto): string
    {
        return $layerDto->blockId;
    }

    protected function layerRemoved(mixed $layerDto): bool
    {
        return $layerDto->removed;
    }

    protected function layerInherits(mixed $layerDto): bool
    {
        return $layerDto->inheritsFromPreviousPublication;
    }

    protected function layerOverridePayload(mixed $layerDto): ?array
    {
        return $layerDto->overridePayload;
    }

    // ─── Template-specific: backfill structural fields on no-layers fallback ──

    protected function noLayersFallback(mixed $snapshotDto): array
    {
        $blocks = array_values($snapshotDto->blocksSnapshotRows);

        return $this->backfillStructuralFields($blocks, $snapshotDto->entityId);
    }

    protected function postProcess(array $blocks, mixed $snapshotDto): array
    {
        return $this->backfillStructuralFields($blocks, $snapshotDto->entityId);
    }

    // ─── Backfill helper (template-domain-only) ───────────────────────────────

    /**
     * Los snapshots de plantilla anteriores al fix se guardaron SIN `block_type`
     * ni los campos de maquetación. `entity_versions` es append-only (no se
     * pueden mutar las versiones publicadas), así que rellenamos esos campos en
     * LECTURA desde los `template_blocks` vivos emparejando por id, solo para
     * bloques a los que les falta `block_type`. Los snapshots nuevos ya vienen
     * completos → no-op.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function backfillStructuralFields(array $blocks, string $templateId): array
    {
        $missingIds = [];
        foreach ($blocks as $b) {
            if (is_array($b) && isset($b['id']) && ! array_key_exists('block_type', $b)) {
                $missingIds[] = (string) $b['id'];
            }
        }
        if ($missingIds === []) {
            return $blocks;
        }

        $live = $this->templateBlockRepository->findByIds($missingIds)
            ->keyBy(fn ($b) => (string) $b->id);

        foreach ($blocks as $i => $b) {
            if (! is_array($b) || ! isset($b['id']) || array_key_exists('block_type', $b)) {
                continue;
            }
            $ref = $live->get((string) $b['id']);
            if ($ref === null) {
                // Bloque borrado de la plantilla viva: se deja tal cual; el
                // consumidor lo trata como 'content' por defecto.
                continue;
            }
            $bt = $ref->block_type;
            $b['block_type'] = $bt instanceof BlockType ? $bt->value : (string) ($bt ?? 'content');
            $b['page_break_after'] = (bool) $ref->page_break_after;
            $b['theme_id'] = $ref->theme_id !== null ? (string) $ref->theme_id : null;
            $b['apply_theme'] = (bool) ($ref->apply_theme ?? true);
            $blocks[$i] = $b;
        }

        return $blocks;
    }
}
