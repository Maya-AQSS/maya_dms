<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Versioning\EntityVersionSnapshotDto;
use App\Enums\BlockType;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;

/**
 * Reconstruye el snapshot efectivo de bloques desde capas incrementales.
 * Accepts scalar IDs and DTOs, not Eloquent models.
 */
final class TemplateVersionBlockLayerResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateVersionBlockLayerRepositoryInterface $layerRepository,
        private readonly TemplateBlockRepositoryInterface $templateBlockRepository,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function resolveBlocksSnapshot(string $entityVersionId): array
    {
        $version = $this->entityVersionRepository->findOrFailAsSnapshot($entityVersionId);

        $layers = $this->layerRepository->listForVersionAsDto($entityVersionId);

        if ($layers->isEmpty()) {
            return $this->backfillStructuralFields(array_values($version->blocksSnapshotRows), $version->entityId);
        }

        $out = [];
        foreach ($layers as $layer) {
            if ($layer->removed) {
                continue;
            }

            $eff = $this->effectiveBlockPayload($layer->templateBlockId, $version);
            if ($eff !== null) {
                $out[] = $eff;
            }
        }

        return $this->backfillStructuralFields($out, $version->entityId);
    }

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

    /**
     * @return array<string, mixed>|null
     */
    private function effectiveBlockPayload(string $templateBlockId, EntityVersionSnapshotDto $version): ?array
    {
        $layer = $this->layerRepository->findForVersionAndBlockAsDto($version->id, $templateBlockId);

        if ($layer === null) {
            return $this->blockFromSnapshotOnly($version, $templateBlockId);
        }

        if ($layer->removed) {
            return null;
        }

        if ($layer->inheritsFromPreviousPublication) {
            if ($version->versionNumber <= 1) {
                return $layer->overridePayload;
            }

            $parent = $this->entityVersionRepository->findOrFailPublishedByEntityAndNumberAsSnapshot(
                Template::class,
                $version->entityId,
                $version->versionNumber - 1,
            );

            return $this->effectiveBlockPayload($templateBlockId, $parent);
        }

        return $layer->overridePayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blockFromSnapshotOnly(EntityVersionSnapshotDto $version, string $templateBlockId): ?array
    {
        foreach ($version->blocksSnapshotRows as $b) {
            if (is_array($b) && isset($b['id']) && (string) $b['id'] === $templateBlockId) {
                return $b;
            }
        }

        return null;
    }
}
