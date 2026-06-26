<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Document;
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
use App\Services\DocumentMigrationService;
use App\Services\DocumentPresentationService;
use App\Services\DocumentReviewerResolutionService;
use App\Services\DocumentReviewService;
use App\Services\DocumentService;
use App\Services\DocumentShareService;
use App\Services\DocumentStateService;
use App\Services\DocumentVersionService;
use App\Services\EntityVersionDestroyService;
use App\Services\TemplateContextResolver;
use App\Support\DocumentReviewModeResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\NotificationPublisher;
use Mockery;
use Tests\TestCase;

class DocumentServiceSubmitTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delegate_owner_throws_when_actor_is_not_current_owner(): void
    {
        $repo = Mockery::mock(DocumentRepositoryInterface::class);
        $doc = new Document;
        $doc->forceFill([
            'id' => 'doc-uuid',
            'owner_id' => 'real-owner-uuid',
            'status' => 'draft',
        ]);

        $repo->shouldReceive('findOrFail')
            ->once()
            ->with('doc-uuid')
            ->andReturn($doc);

        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $blockRepo = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver = Mockery::mock(TemplateContextResolver::class);
        $academicRepo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo = Mockery::mock(TeamReadRepositoryInterface::class);
        $service = new DocumentService($repo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo, Mockery::mock(NotificationPublisher::class), new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class), new EntityVersionDestroyService($entityVersionRepo), new DocumentReviewerResolutionService($entityVersionRepo, $tplRepo, new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class)), new DocumentPresentationService($repo, $entityVersionRepo, Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class)), new DocumentMigrationService($repo, $entityVersionRepo, Mockery::mock(DocumentBlockRepositoryInterface::class), $blockSvc, new DocumentMigrationPayloadResolver($repo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer)), new \App\Support\DocumentAcademicListFilterResolver(Mockery::mock(\App\Services\Contracts\UserProfileServiceInterface::class)));

        $this->expectException(AuthorizationException::class);

        $service->delegateOwner('doc-uuid', 'new-owner-uuid', 'other-actor-uuid');
    }

    public function test_submit_to_review_throws_when_document_is_not_draft(): void
    {
        $repo = Mockery::mock(DocumentRepositoryInterface::class);
        $doc = new Document;
        $doc->forceFill([
            'id' => 'doc-uuid',
            'status' => 'in_review',
        ]);

        $repo->shouldReceive('findOrFail')
            ->once()
            ->with('doc-uuid')
            ->andReturn($doc);

        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $blockRepo = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver = Mockery::mock(TemplateContextResolver::class);
        $academicRepo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo = Mockery::mock(TeamReadRepositoryInterface::class);
        $service = new DocumentService($repo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo, Mockery::mock(NotificationPublisher::class), new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class), new EntityVersionDestroyService($entityVersionRepo), new DocumentReviewerResolutionService($entityVersionRepo, $tplRepo, new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class)), new DocumentPresentationService($repo, $entityVersionRepo, Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class)), new DocumentMigrationService($repo, $entityVersionRepo, Mockery::mock(DocumentBlockRepositoryInterface::class), $blockSvc, new DocumentMigrationPayloadResolver($repo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer)), new \App\Support\DocumentAcademicListFilterResolver(Mockery::mock(\App\Services\Contracts\UserProfileServiceInterface::class)));

        $this->expectException(ValidationException::class);

        $service->submitToReview('doc-uuid', 'actor-uuid', 'Cambios de la versión');
    }
}
