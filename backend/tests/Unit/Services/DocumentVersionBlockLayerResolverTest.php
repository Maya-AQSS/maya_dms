<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Documents\DocumentVersionBlockLayerDto;
use App\DTOs\Documents\DocumentVersionSnapshotDto;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;
use App\Services\DocumentVersionBlockLayerResolver;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for DocumentVersionBlockLayerResolver.
 *
 * Mirror of TemplateVersionBlockLayerResolverTest — same algorithm branches,
 * different DTOs (DocumentVersionSnapshotDto / DocumentVersionBlockLayerDto).
 *
 * Branches covered:
 *   - resolveBlocksSnapshot: no layers → snapshotData['blocks'] returned
 *   - resolveBlocksSnapshot: no layers + missing/empty blocks key → []
 *   - resolveBlocksSnapshot: removed layer in loop is skipped
 *   - effectiveBlockPayload: layer null → blockFromSnapshotOnly
 *   - effectiveBlockPayload: findForVersionAndBlock returns removed layer → null
 *   - effectiveBlockPayload: inherits + versionNumber <= 1 → overridePayload
 *   - effectiveBlockPayload: inherits + versionNumber > 1 → parent recursive
 *   - blockFromSnapshotOnly: block not in snapshot → null
 *   - effectiveBlockPayload: overridePayload null and no inherit → null excluded
 */
final class DocumentVersionBlockLayerResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeSnapshot(array $attributes = []): DocumentVersionSnapshotDto
    {
        $a = array_merge([
            'id' => 'dv-uuid-1',
            'documentId' => 'doc-uuid',
            'versionNumber' => 2,
            'blocks' => [],
        ], $attributes);

        return new DocumentVersionSnapshotDto(
            id: $a['id'],
            documentId: $a['documentId'],
            versionNumber: $a['versionNumber'],
            snapshotData: ['blocks' => $a['blocks']],
        );
    }

    private function makeSnapshotWithRawData(array $attributes = []): DocumentVersionSnapshotDto
    {
        $a = array_merge([
            'id' => 'dv-uuid-1',
            'documentId' => 'doc-uuid',
            'versionNumber' => 2,
            'snapshotData' => [],
        ], $attributes);

        return new DocumentVersionSnapshotDto(
            id: $a['id'],
            documentId: $a['documentId'],
            versionNumber: $a['versionNumber'],
            snapshotData: $a['snapshotData'],
        );
    }

    private function makeLayer(array $attributes = []): DocumentVersionBlockLayerDto
    {
        $a = array_merge([
            'id' => 'layer-uuid-1',
            'documentVersionId' => 'dv-uuid-1',
            'documentBlockId' => 'block-1',
            'removed' => false,
            'overridePayload' => ['id' => 'block-1', 'type' => 'paragraph'],
            'inheritsFromPreviousPublication' => false,
            'sortOrder' => 0,
        ], $attributes);

        return new DocumentVersionBlockLayerDto(
            id: $a['id'],
            documentVersionId: $a['documentVersionId'],
            documentBlockId: $a['documentBlockId'],
            removed: $a['removed'],
            overridePayload: $a['overridePayload'],
            inheritsFromPreviousPublication: $a['inheritsFromPreviousPublication'],
            sortOrder: $a['sortOrder'],
        );
    }

    private function makeResolver(
        DocumentVersionRepositoryInterface $versionRepo,
        DocumentVersionBlockLayerRepositoryInterface $layerRepo,
    ): DocumentVersionBlockLayerResolver {
        return new DocumentVersionBlockLayerResolver($versionRepo, $layerRepo);
    }

    // ─── resolveBlocksSnapshot: no layers → fall back to snapshot blocks ──────

    public function test_resolve_returns_snapshot_blocks_when_no_layers(): void
    {
        $version = $this->makeSnapshot([
            'blocks' => [
                ['id' => 'block-1', 'type' => 'paragraph'],
            ],
        ]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->with('dv-uuid-1')->andReturn(new Collection([]));

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('block-1', $result[0]['id']);
    }

    // ─── resolveBlocksSnapshot: no layers + missing blocks key → [] ──────────

    public function test_resolve_returns_empty_when_no_layers_and_no_blocks_key(): void
    {
        $version = $this->makeSnapshotWithRawData(['snapshotData' => ['other' => 'data']]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->with('dv-uuid-1')->andReturn(new Collection([]));

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── resolveBlocksSnapshot: removed layer in loop is skipped ─────────────

    public function test_resolve_skips_removed_layers(): void
    {
        $version = $this->makeSnapshot();
        $removedLayer = $this->makeLayer(['removed' => true, 'documentBlockId' => 'block-rm']);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$removedLayer]));
        $layerRepo->shouldNotReceive('findForVersionAndBlockAsDto');

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── resolveBlocksSnapshot: active layer with override_payload ───────────

    public function test_resolve_returns_override_payload_for_active_layer(): void
    {
        $version = $this->makeSnapshot();
        $activeLayer = $this->makeLayer([
            'documentBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => false,
            'overridePayload' => ['id' => 'block-1', 'content' => 'hello'],
        ]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$activeLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-1', 'block-1')
            ->andReturn($activeLayer);

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('hello', $result[0]['content']);
    }

    // ─── effectiveBlockPayload: layer null → blockFromSnapshotOnly ────────────

    public function test_effective_block_falls_back_to_snapshot_when_no_layer(): void
    {
        $snapshotBlock = ['id' => 'block-1', 'type' => 'paragraph', 'data' => 'snap'];
        $version = $this->makeSnapshot(['blocks' => [$snapshotBlock]]);

        $listLayer = $this->makeLayer(['documentBlockId' => 'block-1', 'removed' => false]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-1', 'block-1')
            ->andReturn(null);

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('snap', $result[0]['data']);
    }

    // ─── effectiveBlockPayload: findForVersionAndBlock returns removed layer ──

    public function test_effective_block_returns_null_when_found_layer_is_removed(): void
    {
        $version = $this->makeSnapshot();
        $listLayer = $this->makeLayer(['documentBlockId' => 'block-1', 'removed' => false]);
        $removedLayer = $this->makeLayer(['documentBlockId' => 'block-1', 'removed' => true]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-1', 'block-1')
            ->andReturn($removedLayer);

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── effectiveBlockPayload: inherits + versionNumber <= 1 ────────────────

    public function test_effective_block_inherits_from_previous_with_version_number_1_returns_override_payload(): void
    {
        $version = $this->makeSnapshot(['versionNumber' => 1]);
        $listLayer = $this->makeLayer(['documentBlockId' => 'block-1', 'removed' => false]);

        $inheritsLayer = $this->makeLayer([
            'documentBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => true,
            'overridePayload' => ['id' => 'block-1', 'content' => 'v1-payload'],
        ]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-1', 'block-1')
            ->andReturn($inheritsLayer);

        $versionRepo->shouldNotReceive('findByDocumentAndVersionNumberAsSnapshot');

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('v1-payload', $result[0]['content']);
    }

    // ─── effectiveBlockPayload: inherits + versionNumber > 1 → parent ─────────

    public function test_effective_block_inherits_resolves_from_parent_version(): void
    {
        $version = $this->makeSnapshot([
            'id' => 'dv-uuid-3',
            'documentId' => 'doc-uuid',
            'versionNumber' => 3,
        ]);
        $listLayer = $this->makeLayer([
            'documentVersionId' => 'dv-uuid-3',
            'documentBlockId' => 'block-1',
            'removed' => false,
        ]);

        $inheritsLayer = $this->makeLayer([
            'documentVersionId' => 'dv-uuid-3',
            'documentBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => true,
            'overridePayload' => null,
        ]);

        $parentVersion = $this->makeSnapshot([
            'id' => 'dv-uuid-2',
            'documentId' => 'doc-uuid',
            'versionNumber' => 2,
            'blocks' => [
                ['id' => 'block-1', 'content' => 'from-parent-v2'],
            ],
        ]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-3')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->with('dv-uuid-3')->andReturn(new Collection([$listLayer]));

        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-3', 'block-1')
            ->andReturn($inheritsLayer);

        $versionRepo->shouldReceive('findByDocumentAndVersionNumberAsSnapshot')
            ->once()
            ->with('doc-uuid', 2)
            ->andReturn($parentVersion);

        // Recursive call on parent: no layer found → blockFromSnapshotOnly
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-2', 'block-1')
            ->andReturn(null);

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-3');

        $this->assertCount(1, $result);
        $this->assertSame('from-parent-v2', $result[0]['content']);
    }

    // ─── blockFromSnapshotOnly: block not in snapshot → nothing in result ─────

    public function test_block_not_in_snapshot_returns_nothing_in_result(): void
    {
        $version = $this->makeSnapshot([
            'blocks' => [
                ['id' => 'block-other', 'content' => 'other'],
            ],
        ]);
        $listLayer = $this->makeLayer(['documentBlockId' => 'block-missing', 'removed' => false]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-1', 'block-missing')
            ->andReturn(null);

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── effectiveBlockPayload: overridePayload null → null excluded ──────────

    public function test_non_array_override_payload_produces_null_and_is_excluded(): void
    {
        $version = $this->makeSnapshot();
        $listLayer = $this->makeLayer(['documentBlockId' => 'block-1', 'removed' => false]);
        $badLayer = $this->makeLayer([
            'documentBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => false,
            'overridePayload' => null,
        ]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-1', 'block-1')
            ->andReturn($badLayer);

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertSame([], $result);
    }

    // ─── inherits + versionNumber > 1, parent returns null → overridePayload ──

    public function test_effective_block_inherits_parent_null_returns_override_payload(): void
    {
        $version = $this->makeSnapshot(['versionNumber' => 2]);
        $listLayer = $this->makeLayer(['documentBlockId' => 'block-1', 'removed' => false]);

        $inheritsLayer = $this->makeLayer([
            'documentBlockId' => 'block-1',
            'removed' => false,
            'inheritsFromPreviousPublication' => true,
            'overridePayload' => ['id' => 'block-1', 'content' => 'fallback'],
        ]);

        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->with('dv-uuid-1')->andReturn($version);
        $layerRepo->shouldReceive('listForVersionAsDto')->once()->andReturn(new Collection([$listLayer]));
        $layerRepo->shouldReceive('findForVersionAndBlockAsDto')
            ->once()
            ->with('dv-uuid-1', 'block-1')
            ->andReturn($inheritsLayer);

        // Parent lookup returns null (orphaned version)
        $versionRepo->shouldReceive('findByDocumentAndVersionNumberAsSnapshot')
            ->once()
            ->with('doc-uuid', 1)
            ->andReturn(null);

        $result = $this->makeResolver($versionRepo, $layerRepo)->resolveBlocksSnapshot('dv-uuid-1');

        $this->assertCount(1, $result);
        $this->assertSame('fallback', $result[0]['content']);
    }
}
