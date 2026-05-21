<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\BlockPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BlockPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new BlockPolicy;
    }

    public function test_view_any_denied_without_block_index(): void
    {
        $user = $this->makeJwtUser(['template.update']);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_view_any_denied_without_companion_mutation_slug(): void
    {
        $user = $this->makeJwtUser(['block.index']);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_view_any_allowed_with_index_and_template_update(): void
    {
        $user = $this->makeJwtUser(['block.index', 'template.update']);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_any_allowed_with_index_and_document_create(): void
    {
        $user = $this->makeJwtUser(['block.index', 'document.create']);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_show_denied_without_block_show(): void
    {
        $user = $this->makeJwtUser(['template.update']);

        $this->assertFalse($this->policy->view($user));
    }

    public function test_list_for_template_requires_view_on_parent(): void
    {
        $ownerId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser(['block.index', 'template.update']);
        auth()->setUser($user);
        $template = $this->makeTemplate($ownerId);

        $this->assertTrue($this->policy->listForTemplate($user, $template));

        $stranger = $this->makeJwtUser(['block.index', 'template.update']);
        auth()->setUser($stranger);
        $this->assertFalse($this->policy->listForTemplate($stranger, $template));
    }

    public function test_list_for_document_requires_view_on_parent(): void
    {
        $ownerId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser(['block.index', 'document.update']);
        auth()->setUser($user);
        $document = $this->makeDocument($ownerId, $ownerId);

        $this->assertTrue($this->policy->listForDocument($user, $document));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeJwtUser(array $permissions): JwtUser
    {
        return new JwtUser([
            'id' => '11111111-1111-1111-1111-111111111111',
            'email' => null,
            'name' => null,
            'department' => null,
            'permissions' => $permissions,
            'scope' => '',
        ]);
    }

    private function makeTemplate(string $createdBy): Template
    {
        return Template::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla bloques',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by' => $createdBy,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);
    }

    private function makeDocument(string $createdBy, string $ownerId): Document
    {
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla doc',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by' => $createdBy,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        return Document::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'title' => 'Documento bloques',
            'created_by' => $createdBy,
            'owner_id' => $ownerId,
            'status' => 'draft',
        ]);
    }
}
