<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\DocumentShare;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateReviewer;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Documento anclado a template_version_id; al abrir, estructura de esa versión.
 */
class DocumentsTemplateVersionApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    /**
     * @param  list<string>  $realmRoles
     * @param  array<string, mixed>  $extraClaims
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $realmRoles = [], array $extraClaims = []): array
    {
        auth()->forgetUser();

        $this->assignUserPermissions($sub, ['templates.read']);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

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
            $realmRoles,
            $extraClaims,
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @param  array<string, mixed>  $reviewerExtraClaims
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function authHeadersCreatorAndReviewer(
        string $creatorSub,
        string $reviewerSub,
        array $reviewerRealmRoles = [],
        array $reviewerExtraClaims = [],
    ): array {
        auth()->forgetUser();

        $this->assignUserPermissions($creatorSub, ['templates.read']);
        $this->assignUserPermissions($reviewerSub, ['templates.read']);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $tokenCreator = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-c-'.substr($creatorSub, 0, 8),
            $creatorSub,
            'test-issuer',
            'test-audience',
            [],
            [],
        );

        $tokenReviewer = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-r-'.substr($reviewerSub, 0, 8),
            $reviewerSub,
            'test-issuer',
            'test-audience',
            $reviewerRealmRoles,
            $reviewerExtraClaims,
        );

        return [
            ['Authorization' => 'Bearer '.$tokenCreator],
            ['Authorization' => 'Bearer '.$tokenReviewer],
        ];
    }

    /**
     * @param  list<string>  $codes
     */
    private function grantPermissionsForUser(string $userId, array $codes = ['documents.create', 'templates.read', 'users.search']): void
    {
        $now = now();
        foreach ($codes as $code) {
            DB::table('user_permissions')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'permission_code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function test_store_document_requires_published_template_version(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Sin publicar',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Mi doc',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_store_document_can_be_created_with_template_version_only(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
        );

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Normativa',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'heading',
            'title' => 'Bloque publicado',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $versionId = (string) DB::table('template_versions')
            ->where('template_id', $tid)
            ->value('id');

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_version_id' => $versionId,
            'title' => 'Expediente',
        ], $hCreator);

        $createDoc->assertCreated()
            ->assertJsonPath('data.template_id', $tid)
            ->assertJsonPath('data.template_version_id', $versionId);
    }

    public function test_show_document_references_snapshot_from_publish_time(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
        );

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Normativa',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'heading',
            'title' => 'Bloque publicado',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Expediente',
        ], $hCreator);

        $createDoc->assertCreated();
        $docId = $createDoc->json('data.id');
        $versionIdV1 = $createDoc->json('data.template_version_id');
        $this->assertNotEmpty($versionIdV1);
        $createDoc->assertJsonCount(1, 'data.blocks');
        $createDoc->assertJsonPath('data.blocks.0.type', 'heading');
        $createDoc->assertJsonPath('data.blocks.0.title', 'Bloque publicado');

        $show = $this->getJson("/api/v1/documents/{$docId}", $hCreator);
        $show->assertOk();
        $show->assertJsonPath('data.template_version_id', $versionIdV1);
        $show->assertJsonCount(1, 'data.blocks');
        $show->assertJsonPath('data.blocks.0.title', 'Bloque publicado');
        $show->assertJsonPath('data.team', null);
    }

    public function test_show_document_includes_team_when_template_is_team_scoped(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
        );

        $gid = (string) Str::uuid();
        Team::query()->forceCreate([
            'id' => $gid,
            'name' => 'Equipo doc',
            'description' => null,
            'owner_id' => $creatorId,
            'is_department' => true,
        ]);

        TeamMember::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'team_id' => $gid,
            'user_id' => $reviewerId,
            'role' => 'member',
        ]);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla de equipo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Team->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => $gid,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'heading',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Expediente equipo',
        ], $hCreator);

        $createDoc->assertCreated();
        $docId = $createDoc->json('data.id');
        $createDoc->assertJsonPath('data.team.id', $gid);
        $createDoc->assertJsonPath('data.team.name', 'Equipo doc');
        $createDoc->assertJsonPath('data.team.is_department', true);

        $show = $this->getJson("/api/v1/documents/{$docId}", $hCreator);
        $show->assertOk();
        $show->assertJsonPath('data.team.id', $gid);
        $show->assertJsonPath('data.team.name', 'Equipo doc');
        $show->assertJsonPath('data.team.is_department', true);
    }

    public function test_update_document_block_persists_content_in_draft(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla editable',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque editable',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc editable',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $documentBlockId = (string) $createDoc->json('data.blocks.0.document_block_id');

        $payload = [
            'content' => [
                ['type' => 'paragraph', 'content' => 'Contenido actualizado'],
            ],
        ];

        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", $payload, $hCreator)
            ->assertOk()
            ->assertJsonPath('data.document_block_id', $documentBlockId)
            ->assertJsonPath('data.is_filled', true)
            ->assertJsonPath('data.last_edited_by', $creatorId);
    }

    public function test_update_document_block_rejects_locked_blocks(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla bloqueada',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque bloqueado',
            'default_content' => null,
            'block_state' => 'locked',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc bloqueado',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $documentBlockId = (string) $createDoc->json('data.blocks.0.document_block_id');

        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [['type' => 'paragraph', 'content' => 'No debería guardar']],
        ], $hCreator)
            ->assertForbidden();
    }

    public function test_update_document_updates_title(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla update título',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Título inicial',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->putJson("/api/v1/documents/{$docId}", [
            'title' => 'Título actualizado',
        ], $hCreator)
            ->assertOk()
            ->assertJsonPath('data.title', 'Título actualizado');
    }

    public function test_destroy_document_soft_deletes_record(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId, ['documents.create', 'documents.delete', 'templates.read', 'users.search']);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla delete',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Documento borrable',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->deleteJson("/api/v1/documents/{$docId}", [], $hCreator)->assertNoContent();

        $this->assertSoftDeleted('documents', ['id' => $docId]);
    }

    public function test_submit_document_fails_when_mandatory_block_is_empty(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla mandatory',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque obligatorio',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => true,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc mandatory',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['blocks']);
    }

    public function test_submit_document_succeeds_when_mandatory_block_is_filled(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla mandatory ok',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque obligatorio',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => true,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc mandatory ok',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $documentBlockId = (string) $createDoc->json('data.blocks.0.document_block_id');

        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [['type' => 'paragraph', 'content' => 'Contenido obligatorio cumplido']],
        ], $hCreator)->assertOk();

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_approve_last_review_persists_published_document_version_snapshot(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla snapshot doc',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc snapshot',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $reviews = $this->getJson("/api/v1/documents/{$docId}/reviews", $hCreator)
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($reviews);
        $reviewId = (string) $reviews[0]['id'];

        $this->postJson("/api/v1/documents/{$docId}/reviews/{$reviewId}/approve", [
            'changelog' => 'Cierre v1 liberado',
        ], $hReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $row = DB::table('document_versions')->where('document_id', $docId)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->version_number);
        $this->assertSame('published', $row->trigger_event);
        $this->assertSame($reviewerId, $row->triggered_by);
        $this->assertSame('Cierre v1 liberado', $row->notes);

        $raw = $row->snapshot_data;
        $snapshot = is_string($raw) ? json_decode($raw, true) : $raw;
        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('document', $snapshot);
        $this->assertArrayHasKey('blocks', $snapshot);
        $this->assertSame($docId, $snapshot['document']['id']);
        $this->assertSame('published', $snapshot['document']['status']);
        $this->assertNotEmpty($snapshot['blocks']);

        $this->assertSame(1, (int) DB::table('documents')->where('id', $docId)->value('current_version'));

        $versionId = (string) $row->id;
        $show = $this->getJson("/api/v1/documents/{$docId}/versions/{$versionId}", $hCreator)
            ->assertOk()
            ->json('data');
        $this->assertSame($versionId, $show['id']);
        $this->assertArrayHasKey('snapshot_data', $show);
        $this->assertSame('Cierre v1 liberado', $show['changelog']);
    }

    public function test_publish_document_requires_changelog(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla publish changelog',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc publish',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertOk();

        // Tras borrar revisiones el revisor deja de entrar por `document_reviews` en el scope del modelo;
        // un share de lectura mantiene visibilidad para publicar sin filas pendientes.
        DocumentShare::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $docId,
            'user_id' => $reviewerId,
            'permission' => 'read',
            'granted_by' => $creatorId,
        ]);

        DB::table('document_reviews')->where('document_id', $docId)->delete();

        $this->postJson("/api/v1/documents/{$docId}/publish", [], $hReviewer)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['changelog']);

        $this->postJson("/api/v1/documents/{$docId}/publish", [
            'changelog' => 'Publicación directa con notas',
        ], $hReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertSame(
            'Publicación directa con notas',
            (string) DB::table('document_versions')->where('document_id', $docId)->value('notes'),
        );
    }

    public function test_template_version_status_reports_update_when_newer_published_template_version_exists(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla estado versión',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1 plantilla'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc banner',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $v2Id = (string) Str::uuid();
        $now = now();
        DB::table('template_versions')->insert([
            'id' => $v2Id,
            'template_id' => $tid,
            'version_number' => 2,
            'blocks_snapshot' => json_encode([]),
            'changelog' => 'Normativa v2 publicada',
            'published_by' => $reviewerId,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->getJson("/api/v1/documents/{$docId}/template-version-status", $hCreator)
            ->assertOk()
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.changelog', 'Normativa v2 publicada')
            ->assertJsonPath('data.current_version.version_number', 1)
            ->assertJsonPath('data.latest_version.version_number', 2)
            ->assertJsonPath('data.latest_version.id', $v2Id);
    }

    public function test_template_version_status_has_no_update_when_document_on_latest_template_version(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla al día',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc al día',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->getJson("/api/v1/documents/{$docId}/template-version-status", $hCreator)
            ->assertOk()
            ->assertJsonPath('data.has_update', false)
            ->assertJsonPath('data.changelog', null)
            ->assertJsonPath('data.current_version.version_number', 1)
            ->assertJsonPath('data.latest_version.version_number', 1);
    }
}
