<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\Team;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use App\Models\TeamMember;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateReviewer;
use App\Models\TemplateVersion;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class TemplatesApiTest extends TestCase
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

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
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
     * Dos usuarios con tokens firmados con el mismo par RSA (el mock JWKS solo admite una clave activa).
     *
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

    private function seedTemplateReviewer(string $templateId, string $userId, int $stage = 1): void
    {
        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'user_id' => $userId,
            'stage' => $stage,
        ]);
    }

    public function test_user_can_crud_personal_template_via_api(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $create = $this->postJson('/api/v1/templates', [
            'name' => 'Plantilla personal',
            'description' => 'Desc',
        ], $headers);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Plantilla personal')
            ->assertJsonPath('data.visibility_level', TemplateVisibilityLevel::Personal->value);

        $templateId = $create->json('data.id');
        $this->assertNotEmpty($templateId);

        $this->getJson("/api/v1/templates/{$templateId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $templateId);

        $this->getJson('/api/v1/templates', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/templates/{$templateId}", [
            'name' => 'Renombrada',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Renombrada');

        $this->deleteJson("/api/v1/templates/{$templateId}", [], $headers)
            ->assertNoContent();

        $this->assertDatabaseMissing('templates', ['id' => $templateId]);
    }

    public function test_user_without_privileged_role_cannot_create_global_template(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['teacher']);

        $this->postJson('/api/v1/templates', [
            'name' => 'Global prohibida',
            'visibility_level' => TemplateVisibilityLevel::Global->value,
        ], $headers)->assertForbidden();
    }

    public function test_user_with_coordination_permissions_can_create_global_template(): void
    {
        // `ed568442-ece5-4c90-97ca-12c8969bb3a2` tiene `templates.create`/`templates.update` en user_permissions (mock).
        $userId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headers = $this->authHeaders($userId, []);

        $this->postJson('/api/v1/templates', [
            'name' => 'Plantilla global',
            'visibility_level' => TemplateVisibilityLevel::Global->value,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.visibility_level', TemplateVisibilityLevel::Global->value);
    }

    public function test_index_filters_by_status_and_visibility_and_respects_per_page_max(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $t1 = (string) Str::uuid();
        $t2 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $t1,
            'name' => 'A',
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

        Template::query()->forceCreate([
            'id' => $t2,
            'name' => 'B',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->getJson('/api/v1/templates?status=draft', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $t1);

        $this->getJson('/api/v1/templates?visibility_level=personal&status=published', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $t2);

        $this->getJson('/api/v1/templates?per_page=25', $headers)
            ->assertUnprocessable();
    }

    public function test_store_study_visibility_requires_study_id(): void
    {
        $userId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headers = $this->authHeaders($userId, []);

        $this->postJson('/api/v1/templates', [
            'name' => 'Sin estudio',
            'visibility_level' => TemplateVisibilityLevel::Study->value,
        ], $headers)->assertUnprocessable();
    }

    public function test_creator_cannot_change_visibility_to_shared_without_role(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['teacher']);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Mía',
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

        $this->patchJson("/api/v1/templates/{$tid}", [
            'visibility_level' => TemplateVisibilityLevel::Global->value,
        ], $headers)->assertForbidden();
    }

    public function test_clone_creates_draft_copy_preserving_visibility_with_suffix_and_blocks(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);
        $this->assignUserPermissions($userId, ['templates.read', 'templates.create']);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Original',
            'description' => 'D',
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'version' => 2,
            'review_stages' => 1,
            'review_mode' => 'parallel',
        ]);

        $b1 = (string) Str::uuid();
        $b2 = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'B1',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => $b2,
            'template_id' => $tid,
            'title' => 'B2',
            'default_content' => null,
            'block_state' => 'locked',
            'sort_order' => 1,
        ]);

        $response = $this->postJson("/api/v1/templates/{$tid}/clone", [], $headers);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Original (copia)')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.visibility_level', TemplateVisibilityLevel::Global->value)
            ->assertJsonPath('data.review_stages', 1)
            ->assertJsonPath('data.review_mode', 'parallel');

        $copyId = $response->json('data.id');
        $this->assertNotSame($tid, $copyId);
        $this->assertSame(2, TemplateBlock::query()->where('template_id', $copyId)->count());
    }

    public function test_destroy_archives_when_documents_exist(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        $did = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Con docs',
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

        Document::query()->forceCreate([
            'id' => $did,
            'template_id' => $tid,
            'title' => 'Doc',
            'study_id' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        $this->deleteJson("/api/v1/templates/{$tid}", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('templates', [
            'id' => $tid,
            'status' => 'archived',
        ]);
    }

    public function test_destroy_no_content_when_no_documents(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Sin docs',
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

        $this->deleteJson("/api/v1/templates/{$tid}", [], $headers)
            ->assertNoContent();

        $this->assertDatabaseMissing('templates', ['id' => $tid]);
    }

    public function test_team_visibility_requires_existing_team(): void
    {
        $userId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headers = $this->authHeaders($userId, []);

        $gid = (string) Str::uuid();
        Team::query()->forceCreate([
            'id' => $gid,
            'name' => 'G',
            'description' => null,
            'owner_id' => $userId,
            'is_department' => false,
        ]);

        $this->postJson('/api/v1/templates', [
            'name' => 'Por grupo',
            'visibility_level' => TemplateVisibilityLevel::Team->value,
            'team_id' => $gid,
        ], $headers)->assertCreated()->assertJsonPath('data.team_id', $gid);
    }

    public function test_peer_can_view_global_template_as_teacher(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Global compartida',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userA,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher']);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)
            ->assertOk()
            ->assertJsonPath('data.id', $tid);
    }

    public function test_peer_cannot_view_others_personal_template(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Personal ajena',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userA,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher']);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertNotFound();
    }

    public function test_teacher_sees_study_scoped_template_when_jwt_contains_study_id(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $stud = 'study-xyz';

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Por estudio',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => $stud,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userA,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher'], [
            'study_id' => $stud,
        ]);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertOk();
    }

    public function test_teacher_sees_study_scoped_template_when_jwt_study_matches(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $stud = 'study-abc';

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Por estudio',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => $stud,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userA,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher'], [
            'study_id' => $stud,
        ]);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertOk();
    }

    public function test_teacher_sees_team_template_when_member(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $gid = (string) Str::uuid();

        Team::query()->forceCreate([
            'id' => $gid,
            'name' => 'Curso',
            'description' => null,
            'owner_id' => $userA,
            'is_department' => false,
        ]);

        TeamMember::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'team_id' => $gid,
            'user_id' => $userB,
            'role' => 'member',
        ]);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'De grupo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Team->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => $gid,
            'created_by' => $userA,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher']);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)
            ->assertOk()
            ->assertJsonPath('data.team_id', $gid)
            ->assertJsonPath('data.team.id', $gid)
            ->assertJsonPath('data.team.name', 'Curso')
            ->assertJsonPath('data.team.is_department', false);
    }

    public function test_template_publish_requires_changelog_when_in_review(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headersReviewer = $this->authHeaders($reviewerId, []);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'En revisión',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'in_review',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => $bid,
            'template_id' => $tid,
            'title' => 'B',
            'default_content' => ['k' => 'v'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->seedTemplateReviewer($tid, $reviewerId);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headersReviewer)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['changelog']);

        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => '   '], $headersReviewer)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['changelog']);
    }

    public function test_template_review_flow_creates_snapshot_and_history(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$headersCreator, $headersReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
            [],
        );

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Flujo',
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
            'id' => $bid,
            'template_id' => $tid,
            'title' => 'T',
            'default_content' => null,
            'block_state' => 'locked',
            'sort_order' => 0,
        ]);

        $this->seedTemplateReviewer($tid, $reviewerId);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->postJson("/api/v1/templates/{$tid}/publish", [
            'changelog' => 'Primera publicación',
        ], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('template_versions', [
            'template_id' => $tid,
            'version_number' => 1,
            'published_by' => $reviewerId,
        ]);

        $vid = TemplateVersion::query()->where('template_id', $tid)->value('id');
        $this->assertNotEmpty($vid);

        $this->getJson("/api/v1/templates/{$tid}/versions", $headersCreator)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version_number', 1)
            ->assertJsonPath('data.0.changelog', 'Primera publicación');

        $this->getJson("/api/v1/template-versions/{$vid}", $headersCreator)
            ->assertOk()
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.blocks_snapshot.0.id', $bid);
    }

    public function test_template_version_snapshot_cannot_be_updated_via_eloquent(): void
    {
        $userId = (string) Str::uuid();
        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Snap',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $vid = (string) Str::uuid();
        TemplateVersion::query()->forceCreate([
            'id' => $vid,
            'template_id' => $tid,
            'version_number' => 1,
            'blocks_snapshot' => [],
            'changelog' => 'x',
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        $version = TemplateVersion::query()->findOrFail($vid);
        $this->expectException(AuthorizationException::class);
        $version->update(['changelog' => 'hack']);
    }

    public function test_template_version_snapshot_mutation_via_http_returns_403(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Snap HTTP',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $vid = (string) Str::uuid();
        TemplateVersion::query()->forceCreate([
            'id' => $vid,
            'template_id' => $tid,
            'version_number' => 1,
            'blocks_snapshot' => [],
            'changelog' => 'x',
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        $this->putJson("/api/v1/template-versions/{$vid}", ['changelog' => 'hack'], $headers)->assertForbidden();
        $this->patchJson("/api/v1/template-versions/{$vid}", ['changelog' => 'hack'], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/template-versions/{$vid}", [], $headers)->assertForbidden();
    }

    public function test_template_reject_review_returns_to_draft(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$headersCreator, $headersReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
            [],
        );

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Rechazo',
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

        $this->seedTemplateReviewer($tid, $reviewerId);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/reject-review", [], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseCount('template_versions', 0);
    }


    public function test_patch_status_cannot_set_published_without_publish_endpoint(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'No patch publish',
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

        $this->patchJson("/api/v1/templates/{$tid}", ['status' => 'published'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
