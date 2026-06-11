<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\EntityVersion;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Services\EntityVersionDestroyService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for EntityVersionDestroyService::assertAndResetToPublished().
 *
 * The method encapsulates the three shared precondition guards and the
 * entityVersionRepository->update call that both TemplateService and
 * DocumentService perform identically during destroyVersion.
 *
 * Domain-specific restoration (blocks, reviewers, status sync) remains in
 * each Service and is NOT tested here.
 */
final class EntityVersionDestroyServiceTest extends TestCase
{
    private EntityVersionRepositoryInterface&MockInterface $entityVersionRepo;

    private EntityVersionDestroyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $this->service = new EntityVersionDestroyService($this->entityVersionRepo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Guard 1: head must be current working version ─────────────────────────

    public function test_throws_when_head_is_null(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-1',
            head: null,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );
    }

    public function test_throws_when_version_id_does_not_match_head(): void
    {
        $head = $this->makeHead(id: 'ver-HEAD', versionNumber: 0, status: 'draft');

        $this->expectException(ValidationException::class);

        $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-OTHER',
            head: $head,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );
    }

    public function test_throws_when_version_number_is_not_zero(): void
    {
        $head = $this->makeHead(id: 'ver-1', versionNumber: 2, status: 'draft');

        $this->expectException(ValidationException::class);

        $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-1',
            head: $head,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );
    }

    // ── Guard 2: head status must be unpublished ───────────────────────────────

    public function test_throws_when_head_status_is_published(): void
    {
        $this->assertNonDiscardableStatus('published');
    }

    public function test_throws_when_head_status_is_archived(): void
    {
        $this->assertNonDiscardableStatus('archived');
    }

    private function assertNonDiscardableStatus(string $status): void
    {
        $head = $this->makeHead(id: 'ver-1', versionNumber: 0, status: $status);

        $this->expectException(ValidationException::class);

        $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-1',
            head: $head,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );
    }

    public function test_does_not_throw_for_draft_status(): void
    {
        $this->assertDiscardableStatus('draft');
    }

    public function test_does_not_throw_for_in_review_status(): void
    {
        $this->assertDiscardableStatus('in_review');
    }

    public function test_does_not_throw_for_rejected_status(): void
    {
        $this->assertDiscardableStatus('rejected');
    }

    private function assertDiscardableStatus(string $status): void
    {
        $head = $this->makeHead(id: 'ver-1', versionNumber: 0, status: $status);

        $publishedVersion = $this->makePublishedVersion(['template' => ['name' => 'T']]);
        $this->entityVersionRepo->shouldReceive('findLatestPublishedForEntity')
            ->once()
            ->andReturn($publishedVersion);
        $this->entityVersionRepo->shouldReceive('update')
            ->once()
            ->andReturn($publishedVersion);

        $result = $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-1',
            head: $head,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );

        $this->assertIsArray($result);
    }

    // ── Guard 3: a published version must exist ───────────────────────────────

    public function test_throws_when_no_published_version_exists(): void
    {
        $head = $this->makeHead(id: 'ver-1', versionNumber: 0, status: 'draft');

        $this->entityVersionRepo->shouldReceive('findLatestPublishedForEntity')
            ->once()
            ->andReturn(null);

        $this->expectException(ValidationException::class);

        $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-1',
            head: $head,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );
    }

    public function test_throws_when_published_snapshot_data_is_not_array(): void
    {
        $head = $this->makeHead(id: 'ver-1', versionNumber: 0, status: 'draft');

        $published = new EntityVersion;
        $published->snapshot_data = 'not-an-array';

        $this->entityVersionRepo->shouldReceive('findLatestPublishedForEntity')
            ->once()
            ->andReturn($published);

        $this->expectException(ValidationException::class);

        $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-1',
            head: $head,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );
    }

    // ── Happy path: resets head and returns snapshot ──────────────────────────

    public function test_updates_head_to_published_state_and_returns_snapshot(): void
    {
        $head = $this->makeHead(id: 'ver-DRAFT', versionNumber: 0, status: 'draft');
        $snapshot = ['template' => ['name' => 'Plantilla X'], 'blocks' => []];
        $publishedVersion = $this->makePublishedVersion($snapshot);

        $this->entityVersionRepo->shouldReceive('findLatestPublishedForEntity')
            ->with('App\Models\Template', 'ent-1')
            ->once()
            ->andReturn($publishedVersion);

        $this->entityVersionRepo->shouldReceive('update')
            ->once()
            ->with($head, [
                'snapshot_data' => $snapshot,
                'status' => 'published',
                'changelog' => null,
            ])
            ->andReturn($publishedVersion);

        $result = $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Template',
            entityId: 'ent-1',
            targetVersionId: 'ver-DRAFT',
            head: $head,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas.',
            noPublishedMessage: 'No existe una versión publicada.',
        );

        $this->assertSame($snapshot, $result);
    }

    public function test_document_domain_messages_are_forwarded_in_errors(): void
    {
        // Different entity messages (plantilla vs documento) are passed as params
        // and appear in the ValidationException errors bag.
        $this->expectException(ValidationException::class);

        $this->service->assertAndResetToPublished(
            entityClass: 'App\Models\Document',
            entityId: 'doc-1',
            targetVersionId: 'ver-1',
            head: null,
            notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual del documento.',
            statusMessage: 'Solo se pueden descartar versiones no publicadas (draft/in_review/rejected).',
            noPublishedMessage: 'No existe una versión publicada a la que restaurar.',
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeHead(string $id, int $versionNumber, string $status): EntityVersion
    {
        $ev = new EntityVersion;
        $ev->id = $id;
        $ev->version_number = $versionNumber;
        $ev->status = $status;

        return $ev;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function makePublishedVersion(array $snapshot): EntityVersion
    {
        $ev = new EntityVersion;
        $ev->snapshot_data = $snapshot;

        return $ev;
    }
}
