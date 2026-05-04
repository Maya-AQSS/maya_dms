<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentShare;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateVersion;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Compartición de documentos: POST/DELETE shares y efectos en policy de edición / envío.
 */
class DocumentShareApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    /** Par RSA fijo por caso de prueba: varios usuarios deben firmar con la misma clave que expone el mock JWKS. */
    private ?string $testJwtPrivatePem = null;

    private ?string $testJwtPublicPem = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    /**
     * @param  list<string>  $permissionCodes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $permissionCodes = []): array
    {
        auth()->forgetUser();
        $this->assignUserPermissions($sub, $permissionCodes);

        if ($this->testJwtPrivatePem === null) {
            [$this->testJwtPrivatePem, $this->testJwtPublicPem] = $this->generateRsaKeyPairForTests();
        }

        $privatePem = $this->testJwtPrivatePem;
        $publicPem = $this->testJwtPublicPem;

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($sub, 0, 8),
            $sub,
            'test-issuer',
            'test-audience',
            [],
            [],
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @return array{
     *   creatorId: string,
     *   ownerId: string,
     *   collabId: string,
     *   documentId: string,
     *   blockId: string
     * }
     */
    private function seedDraftDocumentWithEditableBlock(): array
    {
        $creatorId = (string) Str::uuid();
        $ownerId = (string) Str::uuid();
        $collabId = (string) Str::uuid();
        $templateId = (string) Str::uuid();
        $blockSnapId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $docBlockId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'T share',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $ownerId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'parallel',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $blockSnapId,
            'template_id' => $templateId,
            'title' => 'B1',
            'default_content' => null,
            'description' => null,
            'block_state' => BlockState::Editable->value,
            'sort_order' => 0,
        ]);

        TemplateVersion::query()->forceCreate([
            'id' => $versionId,
            'template_id' => $templateId,
            'version_number' => 1,
            'blocks_snapshot' => [[
                'id' => $blockSnapId,
                'title' => 'B1',
                'default_content' => null,
                'block_state' => 'editable',
                'sort_order' => 0,
            ]],
            'changelog' => 'v1',
            'published_by' => $ownerId,
            'published_at' => now(),
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'template_version_id' => $versionId,
            'title' => 'Doc compartir',
            'study_id' => null,
            'created_by' => $creatorId,
            'owner_id' => $ownerId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        DocumentBlock::query()->forceCreate([
            'id' => $docBlockId,
            'document_id' => $documentId,
            'template_block_id' => $blockSnapId,
            'content' => null,
            'is_filled' => false,
            'last_edited_by' => null,
            'locked_by' => null,
            'locked_at' => null,
            'sort_order' => 0,
        ]);

        return [
            'creatorId' => $creatorId,
            'ownerId' => $ownerId,
            'collabId' => $collabId,
            'documentId' => $documentId,
            'blockId' => $docBlockId,
        ];
    }

    public function test_only_owner_can_post_shares_even_if_creator(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $h = $this->authHeaders($ctx['creatorId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'read',
        ], $h)->assertForbidden();
    }

    public function test_owner_can_create_share_and_collaborator_sees_document(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'read',
        ], $hOwner)
            ->assertCreated()
            ->assertJsonPath('data.user_id', $ctx['collabId'])
            ->assertJsonPath('data.permission', 'read');

        $hCollab = $this->authHeaders($ctx['collabId'], ['templates.read']);

        $this->getJson("/api/v1/documents/{$ctx['documentId']}", $hCollab)->assertOk();
    }

    public function test_post_share_with_self_returns_422(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['ownerId'],
            'permission' => 'read',
        ], $hOwner)->assertUnprocessable();
    }

    public function test_delete_share_hides_document_for_collaborator(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'read',
        ], $hOwner)->assertCreated();

        $hCollab = $this->authHeaders($ctx['collabId'], ['templates.read']);
        $this->getJson("/api/v1/documents/{$ctx['documentId']}", $hCollab)->assertOk();

        $this->deleteJson("/api/v1/documents/{$ctx['documentId']}/shares/{$ctx['collabId']}", [], $hOwner)
            ->assertNoContent();

        $this->getJson("/api/v1/documents/{$ctx['documentId']}", $hCollab)->assertNotFound();
    }

    public function test_collaborator_with_edit_can_update_block(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'edit',
        ], $hOwner)->assertCreated();

        $hCollab = $this->authHeaders($ctx['collabId'], ['templates.read']);

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => ['type' => 'doc', 'content' => []]],
            $hCollab,
        )->assertOk();
    }

    public function test_collaborator_with_edit_cannot_submit_to_review(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'edit',
        ], $hOwner)->assertCreated();

        $hCollab = $this->authHeaders($ctx['collabId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hCollab)->assertForbidden();
    }

    public function test_collaborator_with_read_cannot_update_document_title(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'read',
        ], $hOwner)->assertCreated();

        $hCollab = $this->authHeaders($ctx['collabId'], ['templates.read']);

        $this->patchJson("/api/v1/documents/{$ctx['documentId']}", [
            'title' => 'Nuevo título',
            'delivery_deadline' => now()->addDay()->toDateString(),
        ], $hCollab)->assertForbidden();
    }

    public function test_owner_can_change_share_permission_via_post_again(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'read',
        ], $hOwner)->assertCreated();

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'edit',
        ], $hOwner)->assertCreated()
            ->assertJsonPath('data.permission', 'edit');

        $row = DocumentShare::query()
            ->where('document_id', $ctx['documentId'])
            ->where('user_id', $ctx['collabId'])
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('edit', $row->permission);
    }

    public function test_documents_index_sets_is_shared_with_me_for_collaborator(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read', 'documents.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'read',
        ], $hOwner)->assertCreated();

        $hCollab = $this->authHeaders($ctx['collabId'], ['templates.read', 'documents.read']);

        $list = $this->getJson('/api/v1/documents', $hCollab)->assertOk();
        $items = $list->json('data');
        $this->assertIsArray($items);
        $row = collect($items)->firstWhere('id', $ctx['documentId']);
        $this->assertNotNull($row);
        $this->assertTrue($row['is_shared_with_me']);
        $this->assertSame('read', $row['share_permission']);
    }

    public function test_documents_index_sets_is_shared_with_me_false_for_owner(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read', 'documents.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'read',
        ], $hOwner)->assertCreated();

        $list = $this->getJson('/api/v1/documents', $hOwner)->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $ctx['documentId']);
        $this->assertNotNull($row);
        $this->assertFalse($row['is_shared_with_me']);
        $this->assertNull($row['share_permission']);
    }

    public function test_document_show_includes_share_metadata_for_collaborator(): void
    {
        $ctx = $this->seedDraftDocumentWithEditableBlock();
        $hOwner = $this->authHeaders($ctx['ownerId'], ['templates.read', 'documents.read']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/shares", [
            'user_id' => $ctx['collabId'],
            'permission' => 'edit',
        ], $hOwner)->assertCreated();

        $hCollab = $this->authHeaders($ctx['collabId'], ['templates.read', 'documents.read']);

        $this->getJson("/api/v1/documents/{$ctx['documentId']}", $hCollab)
            ->assertOk()
            ->assertJsonPath('data.is_shared_with_me', true)
            ->assertJsonPath('data.share_permission', 'edit');
    }
}
