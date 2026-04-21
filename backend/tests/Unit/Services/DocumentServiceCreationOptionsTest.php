<?php

namespace Tests\Unit\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\DocumentService;
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

        $version = new TemplateVersion;
        $version->forceFill(['id' => 'ver-1', 'template_id' => 'tpl-1']);

        $verRepo->shouldReceive('findLatestPublishedForTemplate')
            ->once()
            ->with('tpl-1')
            ->andReturn($version);

        $verRepo->shouldReceive('findLatestPublishedForTemplate')
            ->once()
            ->with('tpl-2')
            ->andReturn(null);

        $snap = Mockery::mock(SnapshotServiceInterface::class);
        $service = new DocumentService($docRepo, $tplRepo, $verRepo, $snap);

        $out = $service->creationOptionsForModule('MOD-1');

        $this->assertCount(1, $out);
        $this->assertSame('tpl-1', $out[0]['template_id']);
        $this->assertSame('ver-1', $out[0]['template_version_id']);
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
        $service = new DocumentService($docRepo, $tplRepo, $verRepo, $snap);

        $this->expectException(ValidationException::class);

        $service->createFromModule('MOD-1', 'user-sub-1');
    }
}

