<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Documents\DocumentDto;
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
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\NotificationPublisher;
use Mockery;
use Tests\TestCase;

class DocumentServicePublishTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_publish_document_throws_when_status_not_draft_or_in_review(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill(['snapshot_data' => ['document' => ['status' => 'published']]]);

        $repo = Mockery::mock(DocumentRepositoryInterface::class);
        $doc = new Document;
        $doc->forceFill(['id' => 'doc-uuid']);
        $doc->setRelation('headVersion', $headEv);

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
        $service = new DocumentService($repo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo, Mockery::mock(NotificationPublisher::class), new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class), new EntityVersionDestroyService($entityVersionRepo), new DocumentReviewerResolutionService($entityVersionRepo, $tplRepo, new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class)), new DocumentPresentationService($repo, $entityVersionRepo, Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class)), new DocumentMigrationService($repo, $entityVersionRepo, Mockery::mock(DocumentBlockRepositoryInterface::class), $blockSvc, new DocumentMigrationPayloadResolver($repo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer)));

        $this->expectException(ValidationException::class);

        $service->publishDocument('doc-uuid', 'actor-uuid', null);
    }

    public function test_publish_document_validates_mandatory_blocks_for_draft_without_reviewers(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill(['snapshot_data' => ['document' => ['status' => 'draft']]]);

        $repo = Mockery::mock(DocumentRepositoryInterface::class);
        $doc = new Document;
        $doc->forceFill([
            'id' => 'doc-uuid',
            'template_version_id' => null,
            'template_id' => 'tpl-uuid',
        ]);
        $doc->setRelation('headVersion', $headEv);

        $repo->shouldReceive('findOrFail')
            ->once()
            ->with('doc-uuid')
            ->andReturn($doc);

        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $tplRepo->shouldReceive('findForDocumentReviewCandidatesWithoutCatalogScope')
            ->once()
            ->with('tpl-uuid')
            ->andReturn(null);

        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $blockSvc->shouldReceive('assertMandatoryBlocksAreFilled')
            ->once()
            ->andThrow(ValidationException::withMessages(['blocks' => ['Bloques vacíos.']]));

        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $blockRepo = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver = Mockery::mock(TemplateContextResolver::class);
        $academicRepo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo = Mockery::mock(TeamReadRepositoryInterface::class);
        $service = new DocumentService($repo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo, Mockery::mock(NotificationPublisher::class), new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class), new EntityVersionDestroyService($entityVersionRepo), new DocumentReviewerResolutionService($entityVersionRepo, $tplRepo, new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class)), new DocumentPresentationService($repo, $entityVersionRepo, Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class)), new DocumentMigrationService($repo, $entityVersionRepo, Mockery::mock(DocumentBlockRepositoryInterface::class), $blockSvc, new DocumentMigrationPayloadResolver($repo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer)));

        $this->expectException(ValidationException::class);

        $service->publishDocument('doc-uuid', 'actor-uuid', null);
    }

    public function test_publish_document_does_not_call_block_validation_when_in_review(): void
    {
        $headEv = new EntityVersion;
        $headEv->forceFill([
            'changelog' => 'cambios desde la versión en curso',
            'snapshot_data' => ['document' => ['status' => 'in_review']],
        ]);

        $repo = Mockery::mock(DocumentRepositoryInterface::class);
        $doc = new Document;
        // current_version precargado: evita el subquery fallback del accessor en la conversión a DTO.
        $doc->forceFill(['id' => 'doc-uuid', 'current_version' => 1, 'submitted_at' => null, 'published_at' => null]);
        $doc->setRelation('headVersion', $headEv);

        $repo->shouldReceive('findOrFail')
            ->twice()
            ->with('doc-uuid')
            ->andReturn($doc);

        $repo->shouldReceive('countPendingReviewsForDocument')
            ->once()
            ->with('doc-uuid')
            ->andReturn(0);

        $repo->shouldReceive('transaction')
            ->andReturnUsing(fn ($cb) => $cb());

        // La relación headVersion ya está seteada en el modelo; la carga es no-op.
        $repo->shouldReceive('loadHeadVersion')
            ->once()
            ->with($doc);

        $repo->shouldReceive('findOrFailForRefreshAfterMutation')
            ->once()
            ->andReturn($doc);

        $repo->shouldReceive('clearHeadVersionChangelog')
            ->once()
            ->with('doc-uuid');

        $stateSvc = Mockery::mock(DocumentStateService::class);
        $stateSvc->shouldReceive('transition')->once();

        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $snap->shouldReceive('createDocumentSnapshot')->once();

        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $blockSvc->shouldNotReceive('assertMandatoryBlocksAreFilled');

        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $blockRepo = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver = Mockery::mock(TemplateContextResolver::class);
        $academicRepo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo = Mockery::mock(TeamReadRepositoryInterface::class);
        $service = new DocumentService($repo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo, Mockery::mock(NotificationPublisher::class), new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class), new EntityVersionDestroyService($entityVersionRepo), new DocumentReviewerResolutionService($entityVersionRepo, $tplRepo, new DocumentReviewModeResolver($entityVersionRepo), Mockery::mock(UserDirectoryRepositoryInterface::class)), new DocumentPresentationService($repo, $entityVersionRepo, Mockery::mock(UserDirectoryRepositoryInterface::class), Mockery::mock(CommentRepositoryInterface::class)), new DocumentMigrationService($repo, $entityVersionRepo, Mockery::mock(DocumentBlockRepositoryInterface::class), $blockSvc, new DocumentMigrationPayloadResolver($repo, $entityVersionRepo, $blockSvc, new DocumentMigrationBlockDiffer)));

        $result = $service->publishDocument('doc-uuid', 'actor-uuid', null);

        $this->assertInstanceOf(DocumentDto::class, $result);
        $this->assertSame('doc-uuid', $result->id);
    }
}
