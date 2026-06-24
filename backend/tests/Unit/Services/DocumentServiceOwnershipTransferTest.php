<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Documents\DocumentDto;
use App\Events\OwnershipTransferred;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentBlockService;
use App\Services\DocumentMigrationBlockDiffer;
use App\Services\DocumentMigrationPayloadResolver;
use App\Services\DocumentReviewerResolutionService;
use App\Services\DocumentReviewService;
use App\Services\DocumentService;
use App\Services\DocumentShareService;
use App\Services\DocumentStateService;
use App\Services\DocumentVersionService;
use App\Services\EntityVersionDestroyService;
use App\Services\TemplateContextResolver;
use App\Support\DocumentReviewModeResolver;
use Illuminate\Support\Facades\Event;
use Maya\Messaging\Publishers\NotificationPublisher;
use Mockery;
use Tests\TestCase;

class DocumentServiceOwnershipTransferTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delegate_owner_dispatches_ownership_transferred_with_resolved_names(): void
    {
        Event::fake([OwnershipTransferred::class]);

        $documentId = 'doc-uuid';
        $actorId = 'titular-actual';
        $newOwnerId = 'titular-nuevo';

        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $userDirectory = Mockery::mock(UserDirectoryRepositoryInterface::class);

        // owner_id se sirve delegado desde el snapshot de la versión cabecera, no del
        // atributo local; por eso montamos headVersion con el titular actual.
        $current = new Document;
        $current->forceFill(['id' => $documentId]);
        $headVersion = new EntityVersion;
        $headVersion->forceFill(['snapshot_data' => ['document' => ['owner_id' => $actorId]]]);
        $current->setRelation('headVersion', $headVersion);

        $updated = new Document;
        // current_version precargado: evita el subquery fallback del accessor en la conversión a DTO.
        $updated->forceFill(['id' => $documentId, 'current_version' => 1, 'submitted_at' => null, 'published_at' => null]);
        $updatedHead = new EntityVersion;
        $updatedHead->forceFill(['snapshot_data' => ['document' => ['owner_id' => $newOwnerId, 'title' => 'Documento de prueba']]]);
        $updated->setRelation('headVersion', $updatedHead);

        $docRepo->shouldReceive('findOrFail')->once()->with($documentId)->andReturn($current);
        $docRepo->shouldReceive('updateOwner')->once()->with($current, $newOwnerId)->andReturn($updated);

        $userDirectory->shouldReceive('findNameById')->with($actorId)->andReturn('Titular Anterior');
        $userDirectory->shouldReceive('findNameById')->with($newOwnerId)->andReturn('Titular Nuevo');

        $notificationPublisher = Mockery::mock(NotificationPublisher::class);
        $notificationPublisher->shouldReceive('send')
            ->once()
            ->withArgs(function (
                string $type,
                ?string $recipientId,
            ) use ($newOwnerId): bool {
                return $type === 'document.ownership_transferred'
                    && $recipientId === $newOwnerId;
            });

        $service = $this->makeService($docRepo, $userDirectory, $notificationPublisher);

        $result = $service->delegateOwner($documentId, $newOwnerId, $actorId);

        $this->assertInstanceOf(DocumentDto::class, $result);
        $this->assertSame($documentId, $result->id);

        Event::assertDispatched(
            OwnershipTransferred::class,
            function (OwnershipTransferred $event) use ($documentId, $actorId, $newOwnerId): bool {
                $payload = $event->toAuditPayload();

                return $event->entityType === 'document'
                    && $event->entityId === $documentId
                    && $event->actorId === $actorId
                    && $payload['action'] === 'ownership_transferred'
                    && $payload['previousValue'] === ['owner_id' => $actorId, 'owner_name' => 'Titular Anterior']
                    && $payload['newValue'] === ['owner_id' => $newOwnerId, 'owner_name' => 'Titular Nuevo'];
            },
        );
    }

    private function makeService(
        DocumentRepositoryInterface $docRepo,
        UserDirectoryRepositoryInterface $userDirectory,
        ?NotificationPublisher $notificationPublisher = null,
    ): DocumentService {
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);

        return new DocumentService(
            $docRepo,
            Mockery::mock(TemplateRepositoryInterface::class),
            Mockery::mock(SnapshotServiceInterface::class),
            $blockSvc,
            Mockery::mock(DocumentVersionService::class),
            Mockery::mock(DocumentShareService::class),
            Mockery::mock(DocumentStateService::class),
            Mockery::mock(DocumentReviewService::class),
            $entityVersionRepo,
            Mockery::mock(DocumentBlockRepositoryInterface::class),
            Mockery::mock(TemplateContextResolver::class),
            Mockery::mock(AcademicHierarchyRepositoryInterface::class),
            Mockery::mock(TeamReadRepositoryInterface::class),
            $notificationPublisher ?? Mockery::mock(NotificationPublisher::class),
            new DocumentReviewModeResolver($entityVersionRepo),
            new DocumentMigrationPayloadResolver($docRepo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer),
            $userDirectory,
            Mockery::mock(CommentRepositoryInterface::class),
            new EntityVersionDestroyService($entityVersionRepo),
            new DocumentReviewerResolutionService($entityVersionRepo, Mockery::mock(TemplateRepositoryInterface::class), new DocumentReviewModeResolver($entityVersionRepo), $userDirectory),
        );
    }
}
