<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentBlockService;
use App\Services\DocumentReviewService;
use App\Services\DocumentService;
use App\Services\DocumentShareService;
use App\Services\DocumentStateService;
use App\Services\DocumentVersionService;
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
        $verRepo = Mockery::mock(TemplateVersionRepositoryInterface::class);
        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);

        $service = new DocumentService($repo, $tplRepo, $verRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc);

        $this->expectException(ValidationException::class);

        $service->submitToReview('doc-uuid', 'actor-uuid');
    }
}
