<?php

namespace Tests\Unit\Services;

use App\Models\EntityVersion;
use App\Models\Template;
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

        // El servicio ahora hace UNA sola query batched para todos los template ids.
        $entityVersionRepo->shouldReceive('findLatestPublishedIdsByVersionables')
            ->once()
            ->with(Template::class, ['tpl-1', 'tpl-2'])
            ->andReturn(['tpl-1' => 'ev-1']);

        $blockRepo       = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver = Mockery::mock(TemplateContextResolver::class);
        $academicRepo    = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo        = Mockery::mock(TeamReadRepositoryInterface::class);
        $teamRepo->shouldReceive('getTeamNamesByIds')
            ->once()
            ->with([])
            ->andReturn([]);
        $service = new DocumentService($docRepo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo);

        $out = $service->creationOptionsForModule('MOD-1');

        $this->assertCount(1, $out);
        $this->assertSame('tpl-1', $out[0]['template_id']);
        $this->assertSame('ev-1', $out[0]['template_version_id']);
        $this->assertSame('00000000-0000-0000-0000-000000000001', $out[0]['process_id']);
    }

    public function test_create_from_module_throws_validation_when_no_template_options_exist(): void
    {
        $docRepo = Mockery::mock(DocumentRepositoryInterface::class);
        $tplRepo = Mockery::mock(TemplateRepositoryInterface::class);

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

        $blockRepo       = Mockery::mock(DocumentBlockRepositoryInterface::class);
        $contextResolver = Mockery::mock(TemplateContextResolver::class);
        $academicRepo    = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $teamRepo        = Mockery::mock(TeamReadRepositoryInterface::class);
        $service = new DocumentService($docRepo, $tplRepo, $snap, $blockSvc, $verSvc, $shareSvc, $stateSvc, $reviewSvc, $entityVersionRepo, $blockRepo, $contextResolver, $academicRepo, $teamRepo);

        $this->expectException(ValidationException::class);

        $service->createFromModule('MOD-1', 'user-sub-1', '00000000-0000-0000-0000-000000000001');
    }
}
