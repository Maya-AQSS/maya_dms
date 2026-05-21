<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * Tests for DocumentBlockController (index, update, destroy).
 *
 * Lines not yet covered (41.4%): index, update, destroy, policy checks.
 */
class DocumentBlockApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;
    use SeedsTemplatePublicationAnchor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes = [
        'document.show',
        'template.show',
        'block.index',
        'block.update',
        'block.delete',
        'document.update',
    ]): array
    {
        auth()->forgetUser();
        $this->assignUserPermissions($sub, $codes);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($sub, 0, 8),
            $sub,
            'test-issuer',
            'test-audience',
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * Seeds a document in draft status with one editable block and one optional block.
     * Returns [ownerId, documentId, editableBlockId, optionalBlockId, templateBlockId].
     *
     * @return array{ownerId: string, documentId: string, editableBlockId: string, optionalBlockId: string, templateBlockId: string, optionalTemplateBlockId: string}
     */
    private function seedDraftDocumentWithBlocks(): array
    {
        $ownerId          = (string) Str::uuid();
        $templateId       = (string) Str::uuid();
        $tplBlockId       = (string) Str::uuid();
        $optTplBlockId    = (string) Str::uuid();
        $documentId       = (string) Str::uuid();
        $editableBlockId  = (string) Str::uuid();
        $optionalBlockId  = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Test Template for DocBlockApi',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $tplBlockId,
            'template_id'     => $templateId,
            'title'           => 'Editable Block',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 0,
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $optTplBlockId,
            'template_id'     => $templateId,
            'title'           => 'Optional Block',
            'default_content' => null,
            'block_state'     => BlockState::Optional->value,
            'sort_order'      => 1,
        ]);

        $anchor = $this->seedCanonicalPublicationForTemplate(
            $templateId,
            1,
            $ownerId,
            [
                [
                    'id'              => $tplBlockId,
                    'title'           => 'Editable Block',
                    'default_content' => null,
                    'block_state'     => BlockState::Editable->value,
                    'sort_order'      => 0,
                    'type'            => '',
                    'mandatory'       => true,
                ],
                [
                    'id'              => $optTplBlockId,
                    'title'           => 'Optional Block',
                    'default_content' => null,
                    'block_state'     => BlockState::Optional->value,
                    'sort_order'      => 1,
                    'type'            => '',
                    'mandatory'       => false,
                ],
            ],
        );

        Document::query()->forceCreate([
            'id'                  => $documentId,
            'process_id'          => '00000000-0000-0000-0000-000000000001',
            'template_id'         => $templateId,
            'template_version_id' => $anchor['entity_version_id'],
            'title'               => 'Test Document for DocBlockApi',
            'created_by'          => $ownerId,
            'owner_id'            => $ownerId,
            'status'              => 'draft',
        ]);

        DocumentBlock::query()->forceCreate([
            'id'                => $editableBlockId,
            'document_id'       => $documentId,
            'template_block_id' => $tplBlockId,
            'content'           => null,
            'is_filled'         => false,
            'sort_order'        => 0,
        ]);

        DocumentBlock::query()->forceCreate([
            'id'                => $optionalBlockId,
            'document_id'       => $documentId,
            'template_block_id' => $optTplBlockId,
            'content'           => null,
            'is_filled'         => false,
            'sort_order'        => 1,
        ]);

        return [
            'ownerId'             => $ownerId,
            'documentId'          => $documentId,
            'editableBlockId'     => $editableBlockId,
            'optionalBlockId'     => $optionalBlockId,
            'templateBlockId'     => $tplBlockId,
            'optionalTemplateBlockId' => $optTplBlockId,
        ];
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/documents/'.Str::uuid().'/blocks')
            ->assertUnauthorized();
    }

    public function test_index_returns_404_for_unknown_document(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->getJson('/api/v1/documents/'.Str::uuid().'/blocks', $headers)
            ->assertNotFound();
    }

    public function test_index_returns_blocks_for_owner(): void
    {
        $ctx     = $this->seedDraftDocumentWithBlocks();
        $headers = $this->authHeaders($ctx['ownerId']);

        $response = $this->getJson("/api/v1/documents/{$ctx['documentId']}/blocks", $headers)
            ->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        // Each entry has the expected keys
        $first = $data[0];
        $this->assertArrayHasKey('document_block_id', $first);
        $this->assertArrayHasKey('template_block_id', $first);
        $this->assertArrayHasKey('block_state', $first);
        $this->assertArrayHasKey('content', $first);
        $this->assertArrayHasKey('is_filled', $first);
        $this->assertArrayHasKey('mandatory', $first);
    }

    public function test_index_returns_404_for_non_owner_because_global_scope_hides_document(): void
    {
        // The Document model has a user_access global scope that excludes documents
        // the authenticated user cannot access — non-owners see 404 (not 403).
        $ctx       = $this->seedDraftDocumentWithBlocks();
        $stranger  = (string) Str::uuid();
        $headers   = $this->authHeaders($stranger);

        $this->getJson("/api/v1/documents/{$ctx['documentId']}/blocks", $headers)
            ->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_requires_authentication(): void
    {
        $docId   = (string) Str::uuid();
        $blockId = (string) Str::uuid();

        $this->putJson("/api/v1/documents/{$docId}/blocks/{$blockId}", ['content' => []])
            ->assertUnauthorized();
    }

    public function test_update_returns_404_for_unknown_document(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['document.show', 'document.update', 'template.show']);

        $this->putJson('/api/v1/documents/'.Str::uuid().'/blocks/'.Str::uuid(), ['content' => []], $headers)
            ->assertNotFound();
    }

    public function test_update_editable_block_persists_content(): void
    {
        $ctx     = $this->seedDraftDocumentWithBlocks();
        $headers = $this->authHeaders($ctx['ownerId'], ['document.show', 'document.update', 'template.show']);

        $newContent = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

        $response = $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['editableBlockId']}",
            ['content' => $newContent],
            $headers,
        )->assertOk();

        $data = $response->json('data');
        $this->assertSame($ctx['editableBlockId'], $data['document_block_id']);
        $this->assertSame(true, $data['is_filled']);
    }

    public function test_update_returns_404_for_non_owner_because_global_scope_hides_document(): void
    {
        // The Document model has a user_access global scope — non-owners see 404 (not 403).
        $ctx      = $this->seedDraftDocumentWithBlocks();
        $stranger = (string) Str::uuid();
        $headers  = $this->authHeaders($stranger, ['document.show', 'document.update', 'template.show']);

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['editableBlockId']}",
            ['content' => ['type' => 'doc']],
            $headers,
        )->assertNotFound();
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $docId   = (string) Str::uuid();
        $blockId = (string) Str::uuid();

        $this->deleteJson("/api/v1/documents/{$docId}/blocks/{$blockId}")
            ->assertUnauthorized();
    }

    public function test_destroy_optional_block_returns_204(): void
    {
        $ctx     = $this->seedDraftDocumentWithBlocks();
        $headers = $this->authHeaders($ctx['ownerId'], ['document.show', 'document.update', 'template.show']);

        $this->deleteJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['optionalBlockId']}",
            [],
            $headers,
        )->assertNoContent();

        $this->assertSoftDeleted('document_blocks', ['id' => $ctx['optionalBlockId']]);
    }

    public function test_destroy_returns_404_for_non_owner_because_global_scope_hides_document(): void
    {
        // The Document model has a user_access global scope — non-owners see 404 (not 403).
        $ctx      = $this->seedDraftDocumentWithBlocks();
        $stranger = (string) Str::uuid();
        $headers  = $this->authHeaders($stranger, ['document.show', 'document.update', 'template.show']);

        $this->deleteJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['optionalBlockId']}",
            [],
            $headers,
        )->assertNotFound();
    }

    public function test_destroy_returns_404_for_unknown_block(): void
    {
        $ctx     = $this->seedDraftDocumentWithBlocks();
        $headers = $this->authHeaders($ctx['ownerId'], ['document.show', 'document.update', 'template.show']);

        $this->deleteJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/".Str::uuid(),
            [],
            $headers,
        )->assertNotFound();
    }
}
