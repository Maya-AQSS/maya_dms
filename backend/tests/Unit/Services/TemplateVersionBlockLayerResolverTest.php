<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\EntityVersion;
use App\Models\Template;
use App\Models\TemplateVersionBlockLayer;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Services\TemplateVersionBlockLayerResolver;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TemplateVersionBlockLayerResolver.
 *
 * Covers uncovered branches (75% → target ≥80%):
 *   - resolveBlocksSnapshot: layer.removed=true is skipped (line 38)
 *   - resolveBlocksSnapshot: layers empty → blocksSnapshotRows() returned (line 31)
 *   - effectiveBlockPayload: layer is null → blockFromSnapshotOnly (line 58)
 *   - effectiveBlockPayload: layer.removed=true → returns null (line 62)
 *   - effectiveBlockPayload: inherits_from_previous_publication + version_number ≤ 1 (line 67)
 *   - blockFromSnapshotOnly: block found in snapshot (line 87-93)
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

    private function makeEntityVersion(array $attributes = []): EntityVersion
    {
        $v = new EntityVersion;
        $v->forceFill(array_merge([
            'id'             => 'ev-uuid-1',
            'versionable_id' => 'tmpl-uuid',
            'version_number' => 2,
            'snapshot_data'  => ['blocks' => []],
        ], $attributes));

        return $v;
    }

    private function makeLayer(array $attributes = []): TemplateVersionBlockLayer
    {
        $layer = new TemplateVersionBlockLayer;
        $layer->forceFill(array_merge([
            'entity_version_id'                => 'ev-uuid-1',
            'template_block_id'                => 'block-1',
            'removed'                          => false,
            'inherits_from_previous_publication' => false,
            'override_payload'                 => ['id' => 'block-1', 'type' => 'paragraph'],
        ], $attributes));

        return $layer;
    }

    private function makeResolver(
        EntityVersionRepositoryInterface $evRepo,
        TemplateVersionBlockLayerRepositoryInterface $layerRepo,
    ): TemplateVersionBlockLayerResolver {
        return new TemplateVersionBlockLayerResolver($evRepo, $layerRepo);
    }

    // ─── resolveBlocksSnapshot: no layers → fall back to snapshot ────────────

    public function test_resolve_returns_snapshot_rows_when_no_layers(): void
    {
        $version = $this->makeEntityVersion([
            'snapshot_data' => ['blocks' => [
                ['id' => 'block-1', 'type' => 'paragraph'],
            ]],
        ]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')->once()->with('ev-uuid-1')->andReturn(new Collection([]));

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('block-1', $result[0]['id']);
    }

    // ─── resolveBlocksSnapshot: removed layer is skipped ─────────────────────

    public function test_resolve_skips_removed_layers(): void
    {
        $version      = $this->makeEntityVersion();
        $removedLayer = $this->makeLayer(['removed' => true, 'template_block_id' => 'block-rm']);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')
            ->once()
            ->andReturn(new Collection([$removedLayer]));

        // effectiveBlockPayload should NOT be called because the layer is removed in the loop
        $layerRepo->shouldNotReceive('findForVersionAndBlock');

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── resolveBlocksSnapshot: active layer with override_payload ───────────

    public function test_resolve_returns_override_payload_for_active_layer(): void
    {
        $version     = $this->makeEntityVersion();
        $activeLayer = $this->makeLayer([
            'template_block_id' => 'block-1',
            'removed'           => false,
            'inherits_from_previous_publication' => false,
            'override_payload'  => ['id' => 'block-1', 'content' => 'hello'],
        ]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')
            ->once()
            ->andReturn(new Collection([$activeLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($activeLayer);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('hello', $result[0]['content']);
    }

    // ─── effectiveBlockPayload: layer is null → blockFromSnapshotOnly ─────────

    public function test_effective_block_falls_back_to_snapshot_when_no_layer(): void
    {
        $snapshotBlock = ['id' => 'block-1', 'type' => 'paragraph', 'data' => 'snap'];
        $version       = $this->makeEntityVersion([
            'snapshot_data' => ['blocks' => [$snapshotBlock]],
        ]);

        // A non-removed layer in the list so the loop calls effectiveBlockPayload
        $listLayer = $this->makeLayer(['template_block_id' => 'block-1', 'removed' => false]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')
            ->once()
            ->andReturn(new Collection([$listLayer]));
        // findForVersionAndBlock returns null → triggers blockFromSnapshotOnly
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn(null);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('snap', $result[0]['data']);
    }

    // ─── effectiveBlockPayload: findForVersionAndBlock returns removed layer ──

    public function test_effective_block_returns_null_when_found_layer_is_removed(): void
    {
        $version      = $this->makeEntityVersion();
        $listLayer    = $this->makeLayer(['template_block_id' => 'block-1', 'removed' => false]);
        $removedLayer = $this->makeLayer(['template_block_id' => 'block-1', 'removed' => true]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')
            ->once()
            ->andReturn(new Collection([$listLayer]));
        // findForVersionAndBlock returns a removed layer → effectiveBlockPayload returns null
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($removedLayer);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        // The removed layer returns null, so nothing is appended to $out
        $this->assertSame([], $result);
    }

    // ─── effectiveBlockPayload: inherits + version_number <= 1 ───────────────

    public function test_effective_block_inherits_from_previous_with_version_number_1_returns_override_payload(): void
    {
        // version_number = 1 → no parent to look up; returns override_payload directly
        $version   = $this->makeEntityVersion(['version_number' => 1]);
        $listLayer = $this->makeLayer(['template_block_id' => 'block-1', 'removed' => false]);

        $inheritsLayer = $this->makeLayer([
            'template_block_id'                => 'block-1',
            'removed'                          => false,
            'inherits_from_previous_publication' => true,
            'override_payload'                 => ['id' => 'block-1', 'content' => 'v1-payload'],
        ]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')
            ->once()
            ->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($inheritsLayer);

        // No call to findOrFailPublishedByEntityAndNumber because version_number = 1
        $evRepo->shouldNotReceive('findOrFailPublishedByEntityAndNumber');

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('v1-payload', $result[0]['content']);
    }

    // ─── effectiveBlockPayload: inherits + version_number > 1 → parent ───────

    public function test_effective_block_inherits_resolves_from_parent_version(): void
    {
        // version_number = 3 → look up parent version 2
        $version   = $this->makeEntityVersion([
            'id'             => 'ev-uuid-3',
            'versionable_id' => 'tmpl-uuid',
            'version_number' => 3,
        ]);
        $listLayer = $this->makeLayer([
            'entity_version_id' => 'ev-uuid-3',
            'template_block_id' => 'block-1',
            'removed'           => false,
        ]);

        $inheritsLayer = $this->makeLayer([
            'entity_version_id'                => 'ev-uuid-3',
            'template_block_id'                => 'block-1',
            'removed'                          => false,
            'inherits_from_previous_publication' => true,
            'override_payload'                 => null,
        ]);

        // Parent version (v2) — has the block in snapshot
        $parentVersion = $this->makeEntityVersion([
            'id'             => 'ev-uuid-2',
            'versionable_id' => 'tmpl-uuid',
            'version_number' => 2,
            'snapshot_data'  => ['blocks' => [
                ['id' => 'block-1', 'content' => 'from-parent-v2'],
            ]],
        ]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-3')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')
            ->once()
            ->with('ev-uuid-3')
            ->andReturn(new Collection([$listLayer]));

        // First call: for the current version's layer
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-3', 'block-1')
            ->andReturn($inheritsLayer);

        // Parent lookup
        $evRepo->shouldReceive('findOrFailPublishedByEntityAndNumber')
            ->once()
            ->with(Template::class, 'tmpl-uuid', 2)
            ->andReturn($parentVersion);

        // Recursive effectiveBlockPayload on parentVersion (ev-uuid-2): no layer found → blockFromSnapshotOnly
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-2', 'block-1')
            ->andReturn(null);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-3');

        $this->assertCount(1, $result);
        $this->assertSame('from-parent-v2', $result[0]['content']);
    }

    // ─── blockFromSnapshotOnly: block not in snapshot → null ─────────────────

    public function test_block_not_in_snapshot_returns_nothing_in_result(): void
    {
        $version   = $this->makeEntityVersion([
            'snapshot_data' => ['blocks' => [
                ['id' => 'block-other', 'content' => 'other'],
            ]],
        ]);
        $listLayer = $this->makeLayer(['template_block_id' => 'block-missing', 'removed' => false]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')->once()->andReturn(new Collection([$listLayer]));
        // No layer found → blockFromSnapshotOnly; block-missing not in snapshot → null
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-1', 'block-missing')
            ->andReturn(null);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── effectiveBlockPayload: override_payload is not an array → null ──────

    public function test_non_array_override_payload_produces_null_and_is_excluded(): void
    {
        $version   = $this->makeEntityVersion();
        $listLayer = $this->makeLayer(['template_block_id' => 'block-1', 'removed' => false]);
        $badLayer  = $this->makeLayer([
            'template_block_id'                => 'block-1',
            'removed'                          => false,
            'inherits_from_previous_publication' => false,
            'override_payload'                 => null, // non-array → null returned
        ]);

        $evRepo    = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFail')->once()->with('ev-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersion')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlock')
            ->once()
            ->with('ev-uuid-1', 'block-1')
            ->andReturn($badLayer);

        $resolver = $this->makeResolver($evRepo, $layerRepo);
        $result   = $resolver->resolveBlocksSnapshot('ev-uuid-1');

        // override_payload is null → effectiveBlockPayload returns null → excluded from $out
        $this->assertSame([], $result);
    }
}
