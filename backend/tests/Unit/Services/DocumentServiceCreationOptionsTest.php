<?php

namespace Tests\Unit\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
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

class DocumentServiceCreationOptionsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creation_options_for_module_returns_only_templates_with_published_version(): void
    {
        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $verRepo = Mockery::mock(TemplateVersionRepositoryInterface::class);

        $templateWithVersion = new Template;
        $templateWithVersion->forceFill([
            'id' => 'tpl-1',
            'name' => 'Plantilla 1',
            'description' => 'Desc 1',
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ]);

        $templateWithoutVersion = new Template;
        $templateWithoutVersion->forceFill([
            'id' => 'tpl-2',
            'name' => 'Plantilla 2',
            'description' => 'Desc 2',
        ]);

        $tplRepo->shouldReceive('listPublishedByModule')
            ->once()
            ->with('MOD-1')
            ->andReturn(collect([$templateWithVersion, $templateWithoutVersion]));

        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $version = new TemplateVersion;
        $version->forceFill([
            'id' => 'ver-1',
            'template_id' => 'tpl-1',
            'version_number' => 1,
        ]);

        $verRepo->shouldReceive('findLatestPublishedForTemplate')
            ->once()
            ->with('tpl-1')
            ->andReturn($version);

        $verRepo->shouldReceive('findLatestPublishedForTemplate')
            ->once()
            ->with('tpl-2')
            ->andReturn(null);

        $verRepo->shouldReceive('findByTemplateIdAndVersionNumber')
            ->once()
            ->with('tpl-1', 1)
            ->andReturn($version);

        $entityVersionRepo->shouldReceive('findLatestPublishedForEntity')
            ->once()
            ->with(Template::class, 'tpl-1')
            ->andReturn(null);

        $entityVersionRepo->shouldReceive('findLatestPublishedForEntity')
            ->once()
            ->with(Template::class, 'tpl-2')
            ->andReturn(null);

        $service = new DocumentService($docRepo, $tplRepo, $verRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo);

        $out = $service->creationOptionsForModule('MOD-1');

        $this->assertCount(1, $out);
        $this->assertSame('tpl-1', $out[0]['template_id']);
        $this->assertSame('ver-1', $out[0]['template_version_id']);
        $this->assertSame('00000000-0000-0000-0000-000000000001', $out[0]['process_id']);
    }

    public function test_create_from_module_throws_validation_when_no_template_options_exist(): void
    {
        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);
        $verRepo = Mockery::mock(TemplateVersionRepositoryInterface::class);

        $tplRepo->shouldReceive('listPublishedByModule')
            ->once()
            ->with('MOD-1')
            ->andReturn(collect());

        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $blockSvc = Mockery::mock(DocumentBlockService::class);
        $verSvc = Mockery::mock(DocumentVersionService::class);
        $shareSvc = Mockery::mock(DocumentShareService::class);
        $stateSvc = Mockery::mock(DocumentStateService::class);
        $reviewSvc = Mockery::mock(DocumentReviewService::class);
        $entityVersionRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $service = new DocumentService($docRepo, $tplRepo, $verRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo);

        $this->expectException(ValidationException::class);

        $service->createFromModule('MOD-1', 'user-sub-1', '00000000-0000-0000-0000-000000000001');
    }
}

