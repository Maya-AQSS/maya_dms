<?php

namespace Tests\Unit\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\DocumentTemplateVersionNumberResolver;
use Mockery;
use Tests\TestCase;

class DocumentTemplateVersionNumberResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_legacy_version_number_when_optional_row_exists(): void
    {
        $verRepo = Mockery::mock(TemplateVersionRepositoryInterface::class);
        $entRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $tv = new TemplateVersion;
        $tv->forceFill(['version_number' => 4]);

        $verRepo->shouldReceive('findOptional')->once()->with('vid')->andReturn($tv);
        $entRepo->shouldReceive('findPublishedMetaByIdForVersionable')->never();

        $resolver = new DocumentTemplateVersionNumberResolver($verRepo, $entRepo);

        $this->assertSame(4, $resolver->resolve('tid', 'vid'));
    }

    public function test_falls_back_to_entity_meta_when_legacy_missing(): void
    {
        $verRepo = Mockery::mock(TemplateVersionRepositoryInterface::class);
        $entRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $verRepo->shouldReceive('findOptional')->once()->with('eid')->andReturn(null);
        $entRepo->shouldReceive('findPublishedMetaByIdForVersionable')
            ->once()
            ->with('eid', Template::class, 'tid')
            ->andReturn([
                'id' => 'eid',
                'version_number' => 2,
                'changelog' => 'x',
            ]);

        $resolver = new DocumentTemplateVersionNumberResolver($verRepo, $entRepo);

        $this->assertSame(2, $resolver->resolve('tid', 'eid'));
    }

    public function test_returns_null_when_no_match(): void
    {
        $verRepo = Mockery::mock(TemplateVersionRepositoryInterface::class);
        $entRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $verRepo->shouldReceive('findOptional')->once()->andReturn(null);
        $entRepo->shouldReceive('findPublishedMetaByIdForVersionable')->once()->andReturn(null);

        $resolver = new DocumentTemplateVersionNumberResolver($verRepo, $entRepo);

        $this->assertNull($resolver->resolve('tid', 'missing'));
    }
}
