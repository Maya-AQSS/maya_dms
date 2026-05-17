<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentBlockService;
use App\Services\DocumentReviewService;
use App\Services\DocumentService;
use App\Services\DocumentShareService;
use App\Services\DocumentStateService;
use App\Services\DocumentVersionService;
use App\Services\TemplateContextResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
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
        $doc  = new Document;
        $doc->forceFill([
            'id'       => 'doc-uuid',
            'owner_id' => 'real-owner-uuid',
            'status'   => 'draft',
        ]);

        $repo->shouldReceive('findOrFail')
            ->once()
            ->with('doc-uuid')
            ->andReturn($doc);

        $tplRepo   = Mockery::mock(TemplateRepositoryInterface::class);
        $snap      = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc  = Mockery::mock(DocumentBlockService::class);
        $verSvc    = Mockery::mock(DocumentVersionService::class);
        $shareSvc  = Mockery::mock(DocumentShareService::class);
        $stateSvc  = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $blockRepo         = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver   = Mockery::mock(TemplateContextResolver::class);
        $academicRepo      = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo          = Mockery::mock(TeamReadRepositoryInterface::class);
        $service = new DocumentService($repo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo);

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
        $blockRepo         = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver   = Mockery::mock(TemplateContextResolver::class);
        $academicRepo      = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo          = Mockery::mock(TeamReadRepositoryInterface::class);
        $service = new DocumentService($repo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo);

        $this->expectException(ValidationException::class);

        $service->submitToReview('doc-uuid', 'actor-uuid');
    }
}
