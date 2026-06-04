<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\Resolvers;

use App\Exceptions\ResourceNotFoundException;
use App\Models\Document;
use App\Models\Process;
use App\Models\Template;
use App\Models\User;
use App\Repositories\Resolvers\PolymorphicResourceResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PolymorphicResourceResolverTest extends TestCase
{
    use RefreshDatabase;

    private PolymorphicResourceResolver $resolver;
    private AuthUser $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PolymorphicResourceResolver();

        // Create a real test user for authentication to bypass global scopes
        $this->testUser = new class extends AuthUser {
            public function getAuthIdentifier(): mixed { return $this->attributes['id'] ?? null; }
        };
        $this->testUser->setAttribute('id', (string) Str::uuid());
    }

    public function test_resolve_returns_document_model(): void
    {
        $this->actingAs($this->testUser);
        $document = $this->createDocument();

        $result = $this->resolver->resolve('document', $document->id);

        $this->assertInstanceOf(Document::class, $result);
        $this->assertEquals($document->id, $result->id);
    }

    public function test_resolve_returns_template_model(): void
    {
        $this->actingAs($this->testUser);
        $template = $this->createTemplate();

        $result = $this->resolver->resolve('template', $template->id);

        $this->assertInstanceOf(Template::class, $result);
        $this->assertEquals($template->id, $result->id);
    }

    public function test_resolve_throws_domain_exception_for_unknown_resource_type(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->resolver->resolve('unknown', 'some-id');
    }

    public function test_resolve_throws_not_found_when_model_not_found(): void
    {
        $this->actingAs($this->testUser);
        $this->expectException(ModelNotFoundException::class);

        $this->resolver->resolve('document', (string) Str::uuid());
    }

    private function createTemplate(): Template
    {
        $userId = $this->testUser->getAuthIdentifier();
        $processId = (string) Str::uuid();
        Process::query()->forceCreate([
            'id' => $processId,
            'code' => 'TEST_PROC',
            'name' => 'Test Process',
            'alias' => 'test_process',
        ]);

        $templateId = (string) Str::uuid();
        return Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => $processId,
            'name' => 'Test Template',
            'created_by' => $userId,
            'status' => 'draft',
            'visibility_level' => 'personal',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
    }

    private function createDocument(): Document
    {
        $template = $this->createTemplate();
        $userId = $this->testUser->getAuthIdentifier();

        $docId = (string) Str::uuid();
        return Document::query()->forceCreate([
            'id' => $docId,
            'created_by' => $userId,
            'owner_id' => $userId,
            'template_id' => $template->id,
            'process_id' => $template->process_id,
            'title' => 'Test Document',
            'status' => 'draft',
        ]);
    }
}
