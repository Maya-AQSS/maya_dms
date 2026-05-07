<?php

namespace Tests\Unit\Services;

use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
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

    public function test_returns_entity_version_number_when_anchor_matches_template(): void
    {
        $entRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $entRepo->shouldReceive('findPublishedMetaByIdForVersionable')
            ->once()
            ->with('eid', Template::class, 'tid')
            ->andReturn([
                'id' => 'eid',
                'version_number' => 2,
                'changelog' => 'x',
            ]);

        $resolver = new DocumentTemplateVersionNumberResolver($entRepo);

        $this->assertSame(2, $resolver->resolve('tid', 'eid'));
    }

    public function test_returns_null_when_entity_meta_missing(): void
    {
        $entRepo = Mockery::mock(EntityVersionRepositoryInterface::class);

        $entRepo->shouldReceive('findPublishedMetaByIdForVersionable')
            ->once()
            ->with('missing', Template::class, 'tid')
            ->andReturn(null);

        $resolver = new DocumentTemplateVersionNumberResolver($entRepo);

        $this->assertNull($resolver->resolve('tid', 'missing'));
    }

    public function test_returns_null_when_template_version_id_empty(): void
    {
        $entRepo = Mockery::mock(EntityVersionRepositoryInterface::class);
        $entRepo->shouldReceive('findPublishedMetaByIdForVersionable')->never();

        $resolver = new DocumentTemplateVersionNumberResolver($entRepo);

        $this->assertNull($resolver->resolve('tid', ''));
    }
}
