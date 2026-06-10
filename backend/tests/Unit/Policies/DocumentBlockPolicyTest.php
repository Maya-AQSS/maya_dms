<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\DocumentBlockPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentBlockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DocumentBlockPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentBlockPolicy;
    }

    // ─── Helper factories ─────────────────────────────────────────────────────

    private function makeJwtUser(string $id): JwtUser
    {
        return new JwtUser([
            'id' => $id,
            'email' => null,
            'name' => null,
            'department' => null,
            // El borrado de bloque exige la capacidad `block.delete` y un slug
            // companion de documento; sobre esa base, el acceso al bloque concreto
            // se decide por propiedad (creador/owner) vía DocumentPolicy::update.
            'permissions' => ['block.delete', 'document.update'],
            'scope' => '',
        ]);
    }

    private function makeDocumentBlock(?string $documentCreatedBy, ?string $documentOwnerId): DocumentBlock
    {
        $block = new DocumentBlock;
        $block->forceFill(['id' => (string) Str::uuid()]);

        if ($documentCreatedBy !== null && $documentOwnerId !== null) {
            $templateId = (string) Str::uuid();

            Template::query()->forceCreate([
                'id' => $templateId,
                'process_id' => '00000000-0000-0000-0000-000000000001',
                'name' => 'Block Policy Template',
                'description' => null,
                'visibility_level' => TemplateVisibilityLevel::Personal->value,
                'created_by' => $documentCreatedBy,
                'status' => 'draft',
                'review_stages' => 0,
                'review_mode' => 'sequential',
            ]);

            $doc = Document::query()->forceCreate([
                'id' => (string) Str::uuid(),
                'process_id' => '00000000-0000-0000-0000-000000000001',
                'template_id' => $templateId,
                'title' => 'Block Policy Document',
                'created_by' => $documentCreatedBy,
                'owner_id' => $documentOwnerId,
                'status' => 'draft',
            ]);

            $block->setRelation('document', $doc);
        } else {
            $block->setRelation('document', null);
        }

        return $block;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_delete_returns_false_when_document_not_loaded(): void
    {
        $user = $this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $block = $this->makeDocumentBlock(null, null);

        $this->assertFalse($this->policy->delete($user, $block));
    }

    public function test_creator_can_delete_own_document_block(): void
    {
        $userId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user = $this->makeJwtUser($userId);
        $block = $this->makeDocumentBlock($userId, $userId);

        $this->assertTrue($this->policy->delete($user, $block));
    }

    public function test_owner_can_delete_when_different_from_creator(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $user = $this->makeJwtUser($ownerId);
        $block = $this->makeDocumentBlock($creatorId, $ownerId);

        $this->assertTrue($this->policy->delete($user, $block));
    }

    public function test_stranger_cannot_delete_another_users_document_block(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $strangerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        $user = $this->makeJwtUser($strangerId);
        $block = $this->makeDocumentBlock($creatorId, $ownerId);

        $this->assertFalse($this->policy->delete($user, $block));
    }

    public function test_former_creator_no_longer_owner_cannot_delete_block(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $user = $this->makeJwtUser($creatorId);
        $block = $this->makeDocumentBlock($creatorId, $ownerId);

        $this->assertFalse($this->policy->delete($user, $block));
    }
}
