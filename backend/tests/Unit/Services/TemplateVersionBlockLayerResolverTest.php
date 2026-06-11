<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Versioning\EntityVersionSnapshotDto;
use App\DTOs\Templates\TemplateVersionBlockLayerDto;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Services\TemplateVersionBlockLayerResolver;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TemplateVersionBlockLayerResolver.
 *
 * The resolver consumes DTOs via the repositories' *AsSnapshot / *AsDto methods,
 * not Eloquent models, so the mocks return EntityVersionSnapshotDto and
 * TemplateVersionBlockLayerDto instances.
 *
 * Covers uncovered branches (75% → target ≥80%):
 *   - resolveBlocksSnapshot: layer.removed=true is skipped (line 38)
 *   - resolveBlocksSnapshot: layers empty → blocksSnapshotRows returned (line 32)
 *   - effectiveBlockPayload: layer is null → blockFromSnapshotOnly (line 58)
 *   - effectiveBlockPayload: layer.removed=true → returns null (line 62)
 *   - effectiveBlockPayload: inherits_from_previous_publication + version_number ≤ 1 (line 67)
 *   - effectiveBlockPayload: inherits_from_previous_publication + version_number > 1 → parent (line 71)
 *   - blockFromSnapshotOnly: block found in snapshot (line 88-93)
 *   - blockFromSnapshotOnly: block not found → null (returns null)
 */
final class TemplateVersionBlockLayerResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeSnapshot(array $attributes = []): EntityVersionSnapshotDto
    {
        $a = array_merge([
            'id' => 'ev-uuid-1',
            'entityId' => 'tmpl-uuid',
            'versionNumber' => 2,
            'blocks' => [],
        ], $attributes);

        return new EntityVersionSnapshotDto(
            id: $a['id'],
            entityId: $a['entityId'],
            versionNumber: $a['versionNumber'],
            blocksSnapshotRows: $a['blocks'],
        );
    }

    private function makeLayer(array $attributes = []): TemplateVersionBlockLayerDto
    {
        $a = array_merge([
            'id' => 'layer-uuid-1',
            'entityVersionId' => 'ev-uuid-1',
            'templateBlockId' => 'block-1',
            'removed' => false,
            'overridePayload' => ['id' => 'block-1', 'type' => 'paragraph'],
            'inheritsFromPreviousPublication' => false,
            'sortOrder' => 0,
        ], $attributes);

        return new TemplateVersionBlockLayerDto(
            id: $a['id'],
            entityVersionId: $a['entityVersionId'],
            templateBlockId: $a['templateBlockId'],
            removed: $a['removed'],
            overridePayload: $a['overridePayload'],
            inheritsFromPreviousPublication: $a['inheritsFromPreviousPublication'],
            sortOrder: $a['sortOrder'],
        );
    }

    private function makeResolver(
        EntityVersionRepositoryInterface $evRepo,
        TemplateVersionBlockLayerRepositoryInterface $layerRepo,
        ?TemplateBlockRepositoryInterface $blockRepo = null,
    ): TemplateVersionBlockLayerResolver {
        if ($blockRepo === null) {
            // Por defecto: sin bloques vivos coincidentes → el backfill en
            // lectura es no-op y la salida del snapshot queda intacta.
            $blockRepo = Mockery::mock(TemplateBlockRepositoryInterface::class);
            $blockRepo->shouldReceive('findByIds')->andReturn(new \Illuminate\Database\Eloquent\Collection);
        }

        return new TemplateVersionBlockLayerResolver($evRepo, $layerRepo, $blockRepo);
    }

    // ─── resolveBlocksSnapshot: no layers → fall back to snapshot ────────────

    public function test_resolve_returns_snapshot_rows_when_no_layers(): void
    {
        $version = $this->makeSnapshot([
            'blocks' => [
                ['id' => 'block-1', 'type' => 'paragraph'],
            ],
        ]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->with('ev-uuid-1')->andReturn(new Collection([]));

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('block-1', $result[0]['id']);
    }

    // ─── resolveBlocksSnapshot: removed layer is skipped ─────────────────────

    public function test_resolve_skips_removed_layers(): void
    {
        $version = $this->makeSnapshot();
        $removedLayer = $this->makeLayer(['removed' => true, 'templateBlockId' => 'block-rm']);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')
            ->once()
            ->andReturn(new Collection([$removedLayer]));

        // effectiveBlockPayload should NOT be called because the layer is removed in the loop
        $layerRepo->shouldNotReceive('findForVersionAndBlockAsDto');

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── resolveBlocksSnapshot: active layer with override_payload ───────────

    public function test_resolve_returns_override_payload_for_active_layer(): void
    {
        $version = $this->makeSnapshot();
        $activeLayer = $this->makeLayer([
            'templateBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => false,
            'overridePayload' => ['id' => 'block-1', 'content' => 'hello'],
        ]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')
            ->once()
            ->andReturn(new Collection([$activeLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($activeLayer);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('hello', $result[0]['content']);
    }

    // ─── effectiveBlockPayload: layer is null → blockFromSnapshotOnly ─────────

    public function test_effective_block_falls_back_to_snapshot_when_no_layer(): void
    {
        $snapshotBlock = ['id' => 'block-1', 'type' => 'paragraph', 'data' => 'snap'];
        $version = $this->makeSnapshot([
            'blocks' => [$snapshotBlock],
        ]);

        // A non-removed layer in the list so the loop calls effectiveBlockPayload
        $listLayer = $this->makeLayer(['templateBlockId' => 'block-1', 'removed' => false]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')
            ->once()
            ->andReturn(new Collection([$listLayer]));
        // findForVersionAndBlockAsDto returns null → triggers blockFromSnapshotOnly
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn(null);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('snap', $result[0]['data']);
    }

    // ─── effectiveBlockPayload: findForVersionAndBlockAsDto returns removed layer ──

    public function test_effective_block_returns_null_when_found_layer_is_removed(): void
    {
        $version = $this->makeSnapshot();
        $listLayer = $this->makeLayer(['templateBlockId' => 'block-1', 'removed' => false]);
        $removedLayer = $this->makeLayer(['templateBlockId' => 'block-1', 'removed' => true]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')
            ->once()
            ->andReturn(new Collection([$listLayer]));
        // findForVersionAndBlockAsDto returns a removed layer → effectiveBlockPayload returns null
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($removedLayer);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        // The removed layer returns null, so nothing is appended to $out
        $this->assertSame([], $result);
    }

    // ─── effectiveBlockPayload: inherits + version_number <= 1 ───────────────

    public function test_effective_block_inherits_from_previous_with_version_number_1_returns_override_payload(): void
    {
        // version_number = 1 → no parent to look up; returns override_payload directly
        $version = $this->makeSnapshot(['versionNumber' => 1]);
        $listLayer = $this->makeLayer(['templateBlockId' => 'block-1', 'removed' => false]);

        $inheritsLayer = $this->makeLayer([
            'templateBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => true,
            'overridePayload' => ['id' => 'block-1', 'content' => 'v1-payload'],
        ]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')
            ->once()
            ->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($inheritsLayer);

        // No call to findOrFailPublishedByEntityAndNumberAsSnapshot because version_number = 1
        $evRepo->shouldNotReceive('findOrFailPublishedByEntityAndNumberAsSnapshot');

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('v1-payload', $result[0]['content']);
    }

    // ─── effectiveBlockPayload: inherits + version_number > 1 → parent ───────

    public function test_effective_block_inherits_resolves_from_parent_version(): void
    {
        // version_number = 3 → look up parent version 2
        $version = $this->makeSnapshot([
            'id' => 'ev-uuid-3',
            'entityId' => 'tmpl-uuid',
            'versionNumber' => 3,
        ]);
        $listLayer = $this->makeLayer([
            'entityVersionId' => 'ev-uuid-3',
            'templateBlockId' => 'block-1',
            'removed' => false,
        ]);

        $inheritsLayer = $this->makeLayer([
            'entityVersionId' => 'ev-uuid-3',
            'templateBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => true,
            'overridePayload' => null,
        ]);

        // Parent version (v2) — has the block in snapshot
        $parentVersion = $this->makeSnapshot([
            'id' => 'ev-uuid-2',
            'entityId' => 'tmpl-uuid',
            'versionNumber' => 2,
            'blocks' => [
                ['id' => 'block-1', 'content' => 'from-parent-v2'],
            ],
        ]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-3')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')
            ->once()
            ->with('ev-uuid-3')
            ->andReturn(new Collection([$listLayer]));

        // First call: for the current version's layer
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-3', 'block-1')
            ->andReturn($inheritsLayer);

        // Parent lookup
        $evRepo->shouldReceive('findOrFailPublishedByEntityAndNumberAsSnapshot')
            ->once()
            ->with(Template::class, 'tmpl-uuid', 2)
            ->andReturn($parentVersion);

        // Recursive effectiveBlockPayload on parentVersion (ev-uuid-2): no layer found → blockFromSnapshotOnly
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-2', 'block-1')
            ->andReturn(null);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-3');

        $this->assertCount(1, $result);
        $this->assertSame('from-parent-v2', $result[0]['content']);
    }

    // ─── blockFromSnapshotOnly: block not in snapshot → null ─────────────────

    public function test_block_not_in_snapshot_returns_nothing_in_result(): void
    {
        $version = $this->makeSnapshot([
            'blocks' => [
                ['id' => 'block-other', 'content' => 'other'],
            ],
        ]);
        $listLayer = $this->makeLayer(['templateBlockId' => 'block-missing', 'removed' => false]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        // No layer found → blockFromSnapshotOnly; block-missing not in snapshot → null
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-1', 'block-missing')
            ->andReturn(null);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── effectiveBlockPayload: override_payload is null → null ──────────────

    public function test_non_array_override_payload_produces_null_and_is_excluded(): void
    {
        $version = $this->makeSnapshot();
        $listLayer = $this->makeLayer(['templateBlockId' => 'block-1', 'removed' => false]);
        $badLayer = $this->makeLayer([
            'templateBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => false,
            'overridePayload' => null, // null payload → null returned
        ]);

        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($badLayer);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        // override_payload is null → effectiveBlockPayload returns null → excluded from $out
        $this->assertSame([], $result);
    }
}
