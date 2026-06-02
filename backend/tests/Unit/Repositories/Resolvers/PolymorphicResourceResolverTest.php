<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\Resolvers;

use App\Models\Document;
use App\Models\Template;
use App\Repositories\Resolvers\PolymorphicResourceResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class PolymorphicResourceResolverTest extends TestCase
{
    private PolymorphicResourceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PolymorphicResourceResolver();
    }

    public function test_resolve_returns_document_model(): void
    {
        $document = Document::factory()->create();

        $result = $this->resolver->resolve('document', $document->id);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertEquals($document->id, $result->id);
    }

    public function test_resolve_returns_template_model(): void
    {
        $template = Template::factory()->create();

        $result = $this->resolver->resolve('template', $template->id);

        $this->assertInstanceOf(Template::class, $result);
        $this->assertEquals($template->id, $result->id);
    }

    public function test_resolve_throws_not_found_for_unknown_resource_type(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver->resolve('unknown', 'some-id');
    }

    public function test_resolve_throws_not_found_when_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->resolver->resolve('document', 'nonexistent-id');
    }
}
