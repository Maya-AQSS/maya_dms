<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Documents\DocumentBlockPayloadDto;
use App\DTOs\Documents\DocumentVersionSnapshotDto;
use App\Models\DocumentVersionBlockLayer;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;
use App\Services\DocumentVersionBlockLayerWriter;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for DocumentVersionBlockLayerWriter.
 *
 * Mirror of TemplateVersionBlockLayerWriterTest — same algorithm branches,
 * different DTOs (DocumentVersionSnapshotDto / DocumentBlockPayloadDto).
 *
 * Branches covered:
 *   - syncLayersForNewPublication: no previous version → full override layers
 *   - syncLayersForNewPublication: previous version, payload unchanged → inherits=true
 *   - syncLayersForNewPublication: previous version, payload changed → inherits=false
 *   - syncLayersForNewPublication: block in previous but absent from draft → removed=true
 *   - syncLayersForNewPublication: previous snapshot has no 'blocks' key → treated as empty previous
 */
final class DocumentVersionBlockLayerWriterTest extends TestCase
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
            'versionNumber' => 1,
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
            'id' => 'dv-uuid-prev',
            'documentId' => 'doc-uuid',
            'versionNumber' => 1,
            'snapshotData' => [],
        ], $attributes);

        return new DocumentVersionSnapshotDto(
            id: $a['id'],
            documentId: $a['documentId'],
            versionNumber: $a['versionNumber'],
            snapshotData: $a['snapshotData'],
        );
    }

    private function makeBlockDto(array $attributes = []): DocumentBlockPayloadDto
    {
        $a = array_merge([
            'blockId' => 'block-1',
            'templateBlockId' => null,
            'content' => null,
            'isFilled' => false,
            'sortOrder' => 0,
            'lastEditedBy' => null,
            'lockedBy' => null,
            'lockedAt' => null,
        ], $attributes);

        return new DocumentBlockPayloadDto(
            blockId: $a['blockId'],
            templateBlockId: $a['templateBlockId'],
            content: $a['content'],
            isFilled: $a['isFilled'],
            sortOrder: $a['sortOrder'],
            lastEditedBy: $a['lastEditedBy'],
            lockedBy: $a['lockedBy'],
            lockedAt: $a['lockedAt'],
        );
    }

    private function makeWriter(
        DocumentRepositoryInterface $docRepo,
        DocumentVersionRepositoryInterface $versionRepo,
        DocumentVersionBlockLayerRepositoryInterface $layerRepo,
    ): DocumentVersionBlockLayerWriter {
        return new DocumentVersionBlockLayerWriter($docRepo, $versionRepo, $layerRepo);
    }

    // ─── No previous version → all blocks get full override layers ───────────

    public function test_sync_creates_full_override_layers_when_no_previous_version(): void
    {
        $createdVersion = $this->makeSnapshot(['versionNumber' => 1]);
        $block = $this->makeBlockDto(['blockId' => 'block-1', 'sortOrder' => 0]);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')
            ->once()->with('dv-uuid-1')->andReturn($createdVersion);

        $docRepo->shouldReceive('findBlocksAsPayloadDtosForDocument')
            ->once()->with('doc-uuid')->andReturn(new Collection([$block]));

        $versionRepo->shouldReceive('findByDocumentAndVersionNumberAsSnapshot')
            ->once()->with('doc-uuid', 0)->andReturn(null);

        $layerRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) use ($block): bool {
                return $attrs['document_version_id'] === 'dv-uuid-1'
                    && $attrs['document_block_id'] === $block->blockId
                    && $attrs['inherits_from_previous_publication'] === false
                    && $attrs['removed'] === false
                    && is_array($attrs['override_payload'])
                    && $attrs['override_payload']['id'] === $block->blockId;
            }))
            ->andReturn(Mockery::mock(DocumentVersionBlockLayer::class));

        $this->makeWriter($docRepo, $versionRepo, $layerRepo)
            ->syncLayersForNewPublication('dv-uuid-1', 'doc-uuid');

        $this->addToAssertionCount(1); // Mockery expectations verified in tearDown via Mockery::close()
    }

    // ─── Previous version, payload unchanged → inherits=true ─────────────────

    public function test_sync_marks_inherits_when_payload_unchanged(): void
    {
        $block = $this->makeBlockDto(['blockId' => 'block-1', 'sortOrder' => 1]);
        $blockPayload = $block->toArray();

        $createdVersion = $this->makeSnapshot(['versionNumber' => 2]);
        $prevSnapshot = $this->makeSnapshot([
            'id' => 'dv-uuid-prev',
            'versionNumber' => 1,
            'blocks' => [$blockPayload],
        ]);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->andReturn($createdVersion);
        $docRepo->shouldReceive('findBlocksAsPayloadDtosForDocument')->once()->andReturn(new Collection([$block]));
        $versionRepo->shouldReceive('findByDocumentAndVersionNumberAsSnapshot')
            ->once()->with('doc-uuid', 1)->andReturn($prevSnapshot);

        $layerRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs): bool {
                return $attrs['inherits_from_previous_publication'] === true
                    && $attrs['override_payload'] === null
                    && $attrs['removed'] === false;
            }))
            ->andReturn(Mockery::mock(DocumentVersionBlockLayer::class));

        $this->makeWriter($docRepo, $versionRepo, $layerRepo)
            ->syncLayersForNewPublication('dv-uuid-1', 'doc-uuid');

        $this->addToAssertionCount(1); // Mockery expectations verified in tearDown via Mockery::close()
    }

    // ─── Previous version, payload changed → inherits=false, override stored ──

    public function test_sync_stores_override_when_payload_changed(): void
    {
        $block = $this->makeBlockDto(['blockId' => 'block-1', 'isFilled' => true, 'sortOrder' => 0]);
        $oldPayload = array_merge($block->toArray(), ['is_filled' => false]);

        $createdVersion = $this->makeSnapshot(['versionNumber' => 2]);
        $prevSnapshot = $this->makeSnapshot([
            'id' => 'dv-uuid-prev',
            'versionNumber' => 1,
            'blocks' => [$oldPayload],
        ]);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->andReturn($createdVersion);
        $docRepo->shouldReceive('findBlocksAsPayloadDtosForDocument')->once()->andReturn(new Collection([$block]));
        $versionRepo->shouldReceive('findByDocumentAndVersionNumberAsSnapshot')->once()->andReturn($prevSnapshot);

        $layerRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs) use ($block): bool {
                return $attrs['inherits_from_previous_publication'] === false
                    && $attrs['removed'] === false
                    && is_array($attrs['override_payload'])
                    && $attrs['override_payload']['id'] === $block->blockId;
            }))
            ->andReturn(Mockery::mock(DocumentVersionBlockLayer::class));

        $this->makeWriter($docRepo, $versionRepo, $layerRepo)
            ->syncLayersForNewPublication('dv-uuid-1', 'doc-uuid');

        $this->addToAssertionCount(1); // Mockery expectations verified in tearDown via Mockery::close()
    }

    // ─── Block in previous but absent from draft → removed=true ──────────────

    public function test_sync_creates_removed_layer_for_deleted_block(): void
    {
        $createdVersion = $this->makeSnapshot(['versionNumber' => 2]);

        $block2 = $this->makeBlockDto(['blockId' => 'block-2', 'sortOrder' => 1]);
        $block2Payload = $block2->toArray();
        $prevSnapshot = $this->makeSnapshot([
            'id' => 'dv-uuid-prev',
            'versionNumber' => 1,
            'blocks' => [
                array_merge($block2Payload, ['id' => 'block-1']),
                $block2Payload,
            ],
        ]);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->andReturn($createdVersion);
        $docRepo->shouldReceive('findBlocksAsPayloadDtosForDocument')
            ->once()->andReturn(new Collection([$block2]));
        $versionRepo->shouldReceive('findByDocumentAndVersionNumberAsSnapshot')->once()->andReturn($prevSnapshot);

        $createdAttrs = [];
        $layerRepo->shouldReceive('create')
            ->twice()
            ->withArgs(function (array $attrs) use (&$createdAttrs): bool {
                $createdAttrs[] = $attrs;

                return true;
            })
            ->andReturn(Mockery::mock(DocumentVersionBlockLayer::class));

        $this->makeWriter($docRepo, $versionRepo, $layerRepo)
            ->syncLayersForNewPublication('dv-uuid-1', 'doc-uuid');

        $removedLayers = array_filter($createdAttrs, fn ($a) => $a['removed'] === true);
        $this->assertCount(1, $removedLayers);
        $removedLayer = array_values($removedLayers)[0];
        $this->assertSame('block-1', $removedLayer['document_block_id']);
        $this->assertSame(0, $removedLayer['sort_order']);
        $this->assertNull($removedLayer['override_payload']);
    }

    // ─── Previous snapshot without 'blocks' key → treated as empty previous ──

    public function test_sync_treats_missing_blocks_key_as_empty_previous(): void
    {
        $createdVersion = $this->makeSnapshot(['versionNumber' => 2]);
        $block = $this->makeBlockDto(['blockId' => 'block-1']);
        $prevSnapshot = $this->makeSnapshotWithRawData(['snapshotData' => ['other' => 'data']]);

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $versionRepo = Mockery::mock(DocumentVersionRepositoryInterface::class);
        $layerRepo = Mockery::mock(DocumentVersionBlockLayerRepositoryInterface::class);

        $versionRepo->shouldReceive('findOrFailAsSnapshot')->once()->andReturn($createdVersion);
        $docRepo->shouldReceive('findBlocksAsPayloadDtosForDocument')->once()->andReturn(new Collection([$block]));
        $versionRepo->shouldReceive('findByDocumentAndVersionNumberAsSnapshot')->once()->andReturn($prevSnapshot);

        // Empty prevById → no deleted blocks; block-1 has no previous row → inherits=false
        $layerRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attrs): bool {
                return $attrs['inherits_from_previous_publication'] === false
                    && $attrs['removed'] === false
                    && is_array($attrs['override_payload']);
            }))
            ->andReturn(Mockery::mock(DocumentVersionBlockLayer::class));

        $this->makeWriter($docRepo, $versionRepo, $layerRepo)
            ->syncLayersForNewPublication('dv-uuid-1', 'doc-uuid');

        $this->addToAssertionCount(1); // Mockery expectations verified in tearDown via Mockery::close()
    }
}
