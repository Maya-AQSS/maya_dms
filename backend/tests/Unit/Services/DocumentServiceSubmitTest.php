<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\DocumentService;
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

        $service = new DocumentService($repo, $tplRepo, $verRepo);

        $this->expectException(ValidationException::class);

        $service->submitToReview('doc-uuid', 'actor-uuid');
    }
}
