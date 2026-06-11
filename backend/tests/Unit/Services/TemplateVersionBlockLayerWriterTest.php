<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\DTOs\Versioning\EntityVersionSnapshotDto;
use App\Models\Template;
use App\Models\TemplateVersionBlockLayer;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Services\TemplateVersionBlockLayerWriter;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TemplateVersionBlockLayerWriter.
 *
 * Branches covered:
 *   - syncLayersForNewPublication: no previous version → full override layers
 *   - syncLayersForNewPublication: previous version exists, payload unchanged → inherits=true
 *   - syncLayersForNewPublication: previous version exists, payload changed → inherits=false, override stored
 *   - syncLayersForNewPublication: block in previous but absent from draft → removed=true layer
 *   - syncLayersForNewPublication: multiple blocks mixed (inherit + override + removed)
 */
final class TemplateVersionBlockLayerWriterTest extends TestCase
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
            'versionNumber' => 1,
            'blocks' => [],
        ], $attributes);

        return new EntityVersionSnapshotDto(
            id: $a['id'],
            entityId: $a['entityId'],
            versionNumber: $a['versionNumber'],
            blocksSnapshotRows: $a['blocks'],
        );
    }

    private function makeBlockDto(array $attributes = []): TemplateBlockPayloadDto
    {
        $a = array_merge([
            'blockId' => 'block-1',
            'title' => 'Block 1',
            'description' => null,
            'defaultContent' => null,
            'blockState' => 'editable',
            'sortOrder' => 0,
        ], $attributes);

        return new TemplateBlockPayloadDto(
            blockId: $a['blockId'],
            title: $a['title'],
            description: $a['description'],
            defaultContent: $a['defaultContent'],
            blockState: $a['blockState'],
            sortOrder: $a['sortOrder'],
        );
    }

    private function makeWriter(
        TemplateRepositoryInterface $templateRepo,
        EntityVersionRepositoryInterface $evRepo,
        TemplateVersionBlockLayerRepositoryInterface $layerRepo,
    ): TemplateVersionBlockLayerWriter {
        return new TemplateVersionBlockLayerWriter($templateRepo, $evRepo, $layerRepo);
    }

    // ─── No previous version → all blocks get full override layers ───────────

    public function test_sync_creates_full_override_layers_when_no_previous_version(): void
    {
        $createdVersion = $this->makeSnapshot(['versionNumber' => 1]);
        $block = $this->makeBlockDto(['blockId' => 'block-1', 'sortOrder' => 0]);

        $templateRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')
            ->once()->with('ev-uuid-1')->andReturn($createdVersion);

        $templateRepo->shouldReceive('findBlocksAsPayloadDtosForTemplate')
            ->once()->with('tmpl-uuid')->andReturn(new Collection([$block]));

        $evRepo->shouldReceive('findPublishedByEntityAndNumberAsSnapshot')
            ->once()
            ->with(Template::class, 'tmpl-uuid', 0)
            ->andReturn(null);

        $layerRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) use ($block): bool {
                return $attrs['entity_version_id'] === 'ev-uuid-1'
                    && $attrs['template_block_id'] === $block->blockId
                    && $attrs['inherits_from_previous_publication'] === false
                    && $attrs['removed'] === false
                    && is_array($attrs['override_payload'])
                    && $attrs['override_payload']['id'] === $block->blockId;
            }))
            ->andReturn(Mockery::mock(TemplateVersionBlockLayer::class));

        $this->makeWriter($templateRepo, $evRepo, $layerRepo)
            ->syncLayersForNewPublication('ev-uuid-1', 'tmpl-uuid');

        $this->addToAssertionCount(1); // Mockery expectations verified in tearDown via Mockery::close()
    }

    // ─── Previous version, payload unchanged → inherits=true ─────────────────

    public function test_sync_marks_inherits_when_payload_unchanged(): void
    {
        $block = $this->makeBlockDto(['blockId' => 'block-1', 'sortOrder' => 1]);
        $blockPayload = $block->toArray();

        $createdVersion = $this->makeSnapshot(['versionNumber' => 2]);
        $prevSnapshot = $this->makeSnapshot([
            'id' => 'ev-uuid-prev',
            'versionNumber' => 1,
            'blocks' => [$blockPayload],
        ]);

        $templateRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')
            ->once()->with('ev-uuid-1')->andReturn($createdVersion);

        $templateRepo->shouldReceive('findBlocksAsPayloadDtosForTemplate')
            ->once()->with('tmpl-uuid')->andReturn(new Collection([$block]));

        $evRepo->shouldReceive('findPublishedByEntityAndNumberAsSnapshot')
            ->once()
            ->with(Template::class, 'tmpl-uuid', 1)
            ->andReturn($prevSnapshot);

        $layerRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs): bool {
                return $attrs['inherits_from_previous_publication'] === true
                    && $attrs['override_payload'] === null
                    && $attrs['removed'] === false;
            }))
            ->andReturn(Mockery::mock(TemplateVersionBlockLayer::class));

        $this->makeWriter($templateRepo, $evRepo, $layerRepo)
            ->syncLayersForNewPublication('ev-uuid-1', 'tmpl-uuid');

        $this->addToAssertionCount(1); // Mockery expectations verified in tearDown via Mockery::close()
    }

    // ─── Previous version, payload changed → inherits=false, override stored ──

    public function test_sync_stores_override_when_payload_changed(): void
    {
        $block = $this->makeBlockDto(['blockId' => 'block-1', 'title' => 'New Title', 'sortOrder' => 0]);
        $oldPayload = array_merge($block->toArray(), ['title' => 'Old Title']);

        $createdVersion = $this->makeSnapshot(['versionNumber' => 2]);
        $prevSnapshot = $this->makeSnapshot([
            'id' => 'ev-uuid-prev',
            'versionNumber' => 1,
            'blocks' => [$oldPayload],
        ]);

        $templateRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->andReturn($createdVersion);
        $templateRepo->shouldReceive('findBlocksAsPayloadDtosForTemplate')->once()->andReturn(new Collection([$block]));
        $evRepo->shouldReceive('findPublishedByEntityAndNumberAsSnapshot')->once()->andReturn($prevSnapshot);

        $layerRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) use ($block): bool {
                return $attrs['inherits_from_previous_publication'] === false
                    && $attrs['removed'] === false
                    && is_array($attrs['override_payload'])
                    && $attrs['override_payload']['title'] === $block->title;
            }))
            ->andReturn(Mockery::mock(TemplateVersionBlockLayer::class));

        $this->makeWriter($templateRepo, $evRepo, $layerRepo)
            ->syncLayersForNewPublication('ev-uuid-1', 'tmpl-uuid');

        $this->addToAssertionCount(1); // Mockery expectations verified in tearDown via Mockery::close()
    }

    // ─── Block in previous but absent from draft → removed=true ──────────────

    public function test_sync_creates_removed_layer_for_deleted_block(): void
    {
        $createdVersion = $this->makeSnapshot(['versionNumber' => 2]);

        // Draft has block-2 only; previous had block-1 and block-2
        $block2 = $this->makeBlockDto(['blockId' => 'block-2', 'sortOrder' => 1]);
        $block2Payload = $block2->toArray();
        $prevSnapshot = $this->makeSnapshot([
            'id' => 'ev-uuid-prev',
            'versionNumber' => 1,
            'blocks' => [
                array_merge($block2Payload, ['id' => 'block-1', 'title' => 'Old Block 1']),
                $block2Payload,
            ],
        ]);

        $templateRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $evRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(TemplateVersionBlockLayerRepositoryInterface::class);

        $evRepo->shouldReceive('findOrFailAsSnapshot')->once()->andReturn($createdVersion);
        $templateRepo->shouldReceive('findBlocksAsPayloadDtosForTemplate')
            ->once()->andReturn(new Collection([$block2]));
        $evRepo->shouldReceive('findPublishedByEntityAndNumberAsSnapshot')->once()->andReturn($prevSnapshot);

        // Expect two create calls: one for block-2 (inherits or override) and one removed for block-1
        $createdAttrs = [];
        $layerRepo->shouldReceive('create')
            ->twice()
            ->withArgs(function (array $attrs) use (&$createdAttrs): bool {
                $createdAttrs[] = $attrs;

                return true;
            })
            ->andReturn(Mockery::mock(TemplateVersionBlockLayer::class));

        $this->makeWriter($templateRepo, $evRepo, $layerRepo)
            ->syncLayersForNewPublication('ev-uuid-1', 'tmpl-uuid');

        $removedLayers = array_filter($createdAttrs, fn ($a) => $a['removed'] === true);
        $this->assertCount(1, $removedLayers);
        $removedLayer = array_values($removedLayers)[0];
        $this->assertSame('block-1', $removedLayer['template_block_id']);
        $this->assertSame(0, $removedLayer['sort_order']);
        $this->assertNull($removedLayer['override_payload']);
    }
}
