<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Team;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use App\Models\TeamMember;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateDocumentReviewer;
use App\Models\TemplateReviewer;
use App\Models\EntityVersion;
use App\Models\User;
use App\Services\TemplateVersionBlockLayerResolver;
use App\Support\TemplateHeadSnapshot;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class TemplatesApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    private function anyStudyId(): string
    {
        $existing = \Illuminate\Support\Facades\DB::table('studies')->value('id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $studyTypeId = (string) Str::uuid();
        $studyId = (string) Str::uuid();

        \Illuminate\Support\Facades\DB::table('study_types')->insertOrIgnore([
            'id' => $studyTypeId,
            'name' => 'Tipo test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('studies')->insertOrIgnore([
            'id' => $studyId,
            'study_type_id' => $studyTypeId,
            'name' => 'Estudio test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $studyId;
    }

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

        $this->assignUserPermissions($sub, ['template.show']);

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

        $this->assignUserPermissions($creatorSub, ['template.show']);
        $this->assignUserPermissions($reviewerSub, ['template.show']);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

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

    /**
     * @return array<string, mixed>
     */
    private function getTemplateEntityVersionSnapshot(string $templateId, int $versionNumber): array
    {
        $entityVersion = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $templateId)
            ->where('version_number', $versionNumber)
            ->first();

        $this->assertNotNull($entityVersion);

        $snapshot = is_string($entityVersion->snapshot_data)
            ? json_decode($entityVersion->snapshot_data, true)
            : $entityVersion->snapshot_data;

        $this->assertIsArray($snapshot);

        return $snapshot;
    }

    private function setHeadTemplateStatusDraft(string $templateId): void
    {
        $ev = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $templateId)
            ->where('version_number', 0)
            ->firstOrFail();
        $snapshotData = TemplateHeadSnapshot::mergeTemplateKey($ev->snapshot_data ?? [], ['status' => 'draft']);
        $ev->update([
            'status' => 'draft',
            'snapshot_data' => $snapshotData,
        ]);
    }

    public function test_user_can_crud_personal_template_via_api(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $create = $this->postJson('/api/v1/templates', [
            'name' => 'Plantilla personal',
            'description' => 'Desc',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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

        $this->assertSoftDeleted('templates', ['id' => $templateId]);
    }

    public function test_user_without_privileged_role_cannot_create_global_template(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['teacher']);

        $this->postJson('/api/v1/templates', [
            'name' => 'Global prohibida',
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.visibility_level', TemplateVisibilityLevel::Global->value);
    }

    public function test_store_requires_process_id(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->postJson('/api/v1/templates', [
            'name' => 'Plantilla sin proceso',
            'delivery_deadline' => now()->addDay()->toDateString(),
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['process_id']);
    }

    public function test_store_requires_existing_process_id(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->postJson('/api/v1/templates', [
            'name' => 'Plantilla con proceso inexistente',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000999',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['process_id']);
    }

    public function test_index_filters_by_status_and_visibility(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $t1 = (string) Str::uuid();
        $t2 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $t1,
            'name' => 'A',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Template::query()->forceCreate([
            'id' => $t2,
            'name' => 'B',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
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
    }

    public function test_index_usable_for_documents_returns_templates_with_published_versions_even_if_live_is_draft(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $withPublishedVersion = (string) Str::uuid();
        $withoutPublishedVersion = (string) Str::uuid();
        $archivedWithPublishedVersion = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $withPublishedVersion,
            'name' => 'Con publicada y head draft',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Template::query()->forceCreate([
            'id' => $withoutPublishedVersion,
            'name' => 'Sin versiones publicadas',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Template::query()->forceCreate([
            'id' => $archivedWithPublishedVersion,
            'name' => 'Archivada',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'archived',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        DB::table('entity_versions')->insert([
            [
                'id' => (string) Str::uuid(),
                'versionable_type' => Template::class,
                'versionable_id' => $withPublishedVersion,
                'version_number' => 1,
                'base_version_id' => null,
                'change_set' => null,
                'status' => 'published',
                'created_by' => $userId,
                'published_by' => $userId,
                'published_at' => now(),
                'changelog' => 'v1',
                'snapshot_data' => json_encode(['template' => ['id' => $withPublishedVersion]], JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'versionable_type' => Template::class,
                'versionable_id' => $archivedWithPublishedVersion,
                'version_number' => 1,
                'base_version_id' => null,
                'change_set' => null,
                'status' => 'published',
                'created_by' => $userId,
                'published_by' => $userId,
                'published_at' => now(),
                'changelog' => 'v1',
                'snapshot_data' => json_encode(['template' => ['id' => $archivedWithPublishedVersion]], JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/v1/templates?usable_for_documents=1', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withPublishedVersion);
    }

    public function test_index_includes_has_review_comments_flag(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['teacher']);
        $this->assignUserPermissions($userId, ['template.show']);

        $withComments = (string) Str::uuid();
        $withoutComments = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $withComments,
            'name' => 'Con comentarios',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Template::query()->forceCreate([
            'id' => $withoutComments,
            'name' => 'Sin comentarios',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Comment::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'commentable_type' => Template::class,
            'commentable_id' => $withComments,
            'commentable_version' => 1,
            'blockable_type' => null,
            'blockable_id' => null,
            'parent_id' => null,
            'author_id' => $userId,
            'body' => 'Comentario de revision abierto',        ]);

        $this->getJson('/api/v1/templates', $headers)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $withComments,
                'has_review_comments' => true,
            ])
            ->assertJsonFragment([
                'id' => $withoutComments,
                'has_review_comments' => false,
            ]);
    }

    public function test_store_study_visibility_requires_study_id(): void
    {
        $userId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headers = $this->authHeaders($userId, []);

        $this->postJson('/api/v1/templates', [
            'name' => 'Sin estudio',
            'visibility_level' => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)->assertUnprocessable();
    }

    public function test_creator_cannot_change_visibility_to_shared_without_role(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['teacher']);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'M?a',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
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
        $this->assignUserPermissions($userId, ['template.show', 'template.create']);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Original',
            'description' => 'D',
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'review_stages' => 1,
            'review_mode' => 'parallel',
        ]);
        $head = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        $head->snapshot_data = TemplateHeadSnapshot::mergeTemplateKey(
            $head->snapshot_data ?? [],
            ['delivery_deadline' => now()->addDay()->toDateString()],
        );
        $head->save();

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
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.visibility_level', TemplateVisibilityLevel::Global->value)
            ->assertJsonPath('data.review_stages', 1)
            ->assertJsonPath('data.review_mode', 'parallel');

        $copyId = $response->json('data.id');
        $this->assertNotSame($tid, $copyId);
        $this->assertSame(2, TemplateBlock::query()->where('template_id', $copyId)->count());
    }

    public function test_clone_template_materializes_from_latest_published_snapshot_when_live_differs(): void
    {
        $creatorId = (string) Str::uuid();
        $headersCreator = $this->authHeaders($creatorId, []);
        $this->assignUserPermissions($creatorId, ['template.show', 'template.create']);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        $publishedDeadline = now()->addDay()->toDateString();
        $liveOnlyDeadline = now()->addDays(10)->toDateString();
        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Nombre al publicar',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        $head = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        $head->snapshot_data = TemplateHeadSnapshot::mergeTemplateKey(
            $head->snapshot_data ?? [],
            ['delivery_deadline' => $publishedDeadline],
        );
        $head->save();

        TemplateBlock::query()->forceCreate([
            'id' => $bid,
            'template_id' => $tid,
            'title' => 'Titulo publicado',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headersCreator)->assertOk();

        $headEv = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        $snapshotData = TemplateHeadSnapshot::mergeTemplateKey($headEv->snapshot_data ?? [], [
            'name' => 'Nombre solo en vivo',
            'delivery_deadline' => $liveOnlyDeadline,
            'review_stages' => 4,
            'review_mode' => 'parallel',
        ]);
        $headEv->update(['snapshot_data' => $snapshotData]);

        DB::table('template_blocks')->where('id', $bid)->update(['title' => 'Titulo solo en vivo']);

        $response = $this->postJson("/api/v1/templates/{$tid}/clone", [], $headersCreator);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nombre al publicar (copia)')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.review_stages', 0)
            ->assertJsonPath('data.review_mode', 'sequential');
        $this->assertStringStartsWith(
            $publishedDeadline,
            (string) $response->json('data.delivery_deadline'),
        );

        $copyId = (string) $response->json('data.id');
        $this->assertNotSame($tid, $copyId);

        $this->assertSame(
            'Titulo publicado',
            (string) DB::table('template_blocks')->where('template_id', $copyId)->value('title'),
        );
    }

    public function test_post_template_new_version_sets_draft_when_published(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId, []);
        $this->assignUserPermissions($creatorId, ['template.show', 'template.create']);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'T',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        $headVersionInit = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        $headVersionInit->snapshot_data = TemplateHeadSnapshot::mergeTemplateKey(
            $headVersionInit->snapshot_data ?? [],
            ['delivery_deadline' => now()->addDay()->toDateString()],
        );
        $headVersionInit->save();
        $head = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        $head->snapshot_data = TemplateHeadSnapshot::mergeTemplateKey(
            $head->snapshot_data ?? [],
            ['delivery_deadline' => now()->addDay()->toDateString()],
        );
        $head->save();

        TemplateBlock::query()->forceCreate([
            'id' => $bid,
            'template_id' => $tid,
            'title' => 'B',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)->assertOk();

        $this->postJson("/api/v1/templates/{$tid}/new-version", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame('draft', (string) DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->value('status'));
    }

    public function test_post_template_new_version_assigns_current_actor_as_working_creator(): void
    {
        $originalCreatorId = (string) Str::uuid();
        $actorId = (string) Str::uuid();
        $studyId = $this->anyStudyId();
        $actorHeaders = $this->authHeaders($actorId, []);
        $this->assignUserPermissions($actorId, ['template.show', 'template.update', 'template.create']);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'T',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => $studyId,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $originalCreatorId,
            'status' => 'published',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $bid,
            'template_id' => $tid,
            'title' => 'B',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $head = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        $head->status = 'published';
        $head->snapshot_data = TemplateHeadSnapshot::mergeTemplateKey(
            $head->snapshot_data ?? [],
            [
                'status' => 'published',
                'delivery_deadline' => now()->addDay()->toDateString(),
            ],
        );
        $head->save();
        TemplateReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $actorId,
            'stage' => 1,
            'status' => 'pending',
        ]);
        DB::table('user_studies')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $actorId,
            'study_id' => $studyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/templates/{$tid}/new-version", [], $actorHeaders)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by', $actorId);

        $this->assertSame($actorId, (string) Template::query()->findOrFail($tid)->created_by);
    }

    public function test_delete_template_version_discards_live_draft_and_restores_last_published_snapshot(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId, []);
        $this->assignUserPermissions($creatorId, ['template.show', 'template.create']);

        $reviewerTemplatePublished = (string) Str::uuid();
        $reviewerDocumentPublished = (string) Str::uuid();
        $reviewerTemplateLive = (string) Str::uuid();
        $reviewerDocumentLive = (string) Str::uuid();
        $publishedBlockId = (string) Str::uuid();
        $liveOnlyBlockId = (string) Str::uuid();

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla con borrador descartable',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $reviewerTemplateLive,
            'stage' => 1,
            'status' => 'pending',
        ]);
        TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $reviewerDocumentLive,
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => $liveOnlyBlockId,
            'template_id' => $tid,
            'title' => 'Bloque solo live',
            'description' => null,
            'default_content' => ['delta' => true],
            'block_state' => 'editable',
            'sort_order' => 99,
        ]);

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $creatorId,
            'published_at' => now(),
            'changelog' => 'v1',
            'snapshot_data' => json_encode([
                'template' => [
                    'id' => $tid,
                    'process_id' => '00000000-0000-0000-0000-000000000001',
                    'name' => 'Plantilla publicada v1',
                    'description' => null,
                    'visibility_level' => TemplateVisibilityLevel::Personal->value,
                    'delivery_deadline' => null,
                    'study_type_id' => null,
                    'study_id' => null,
                    'module_id' => null,
                    'team_id' => null,
                    'status' => 'published',
                    'version' => 1,
                    'created_by' => $creatorId,
                ],
                'blocks' => [
                    [
                        'id' => $publishedBlockId,
                        'template_id' => $tid,
                        'title' => 'Bloque publicado',
                        'description' => ['ops' => []],
                        'default_content' => ['ops' => [['insert' => 'v1']]],
                        'block_state' => 'locked',
                        'sort_order' => 1,
                    ],
                ],
                'reviewers' => [
                    'template_reviewers' => [
                        ['user_id' => $reviewerTemplatePublished, 'stage' => 1, 'status' => 'approved'],
                    ],
                    'document_reviewers' => [
                        ['user_id' => $reviewerDocumentPublished],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headId = (string) DB::table('templates')->where('id', $tid)->value('head_entity_version_id');
        $this->assertNotEmpty($headId);

        $this->deleteJson("/api/v1/templates/{$tid}/versions/{$headId}", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.name', 'Plantilla publicada v1');

        $this->assertDatabaseHas('template_blocks', [
            'id' => $publishedBlockId,
            'template_id' => $tid,
            'title' => 'Bloque publicado',
            'block_state' => 'locked',
            'sort_order' => 1,
        ]);
        // TemplateBlock usa SoftDeletes; tras el rollback el bloque live se marca
        // como deleted, no se elimina físicamente. Validamos el soft-delete.
        $this->assertSoftDeleted('template_blocks', [
            'id' => $liveOnlyBlockId,
            'template_id' => $tid,
        ]);

        $this->assertDatabaseHas('template_reviewers', [
            'template_id' => $tid,
            'user_id' => $reviewerTemplatePublished,
            'stage' => 1,
        ]);
        $this->assertDatabaseMissing('template_reviewers', [
            'template_id' => $tid,
            'user_id' => $reviewerTemplateLive,
        ]);
        $this->assertDatabaseHas('template_document_reviewers', [
            'template_id' => $tid,
            'user_id' => $reviewerDocumentPublished,
        ]);
        $this->assertDatabaseMissing('template_document_reviewers', [
            'template_id' => $tid,
            'user_id' => $reviewerDocumentLive,
        ]);
    }

    public function test_delete_template_version_preserves_existing_reviewers_when_published_snapshot_has_no_reviewers_section(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId, []);
        $this->assignUserPermissions($creatorId, ['template.show', 'template.create']);

        $reviewerTemplateLive = (string) Str::uuid();
        $reviewerDocumentLive = (string) Str::uuid();
        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla reviewers legacy',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);
        TemplateReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $reviewerTemplateLive,
            'stage' => 1,
            'status' => 'pending',
        ]);
        TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $reviewerDocumentLive,
        ]);

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $creatorId,
            'published_at' => now(),
            'changelog' => 'v1',
            'snapshot_data' => json_encode([
                'template' => [
                    'id' => $tid,
                    'process_id' => '00000000-0000-0000-0000-000000000001',
                    'name' => 'Plantilla publicada v1',
                    'visibility_level' => TemplateVisibilityLevel::Personal->value,
                    'status' => 'published',
                    'version' => 1,
                    'created_by' => $creatorId,
                ],
                'blocks' => [],
            ], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headId = (string) DB::table('templates')->where('id', $tid)->value('head_entity_version_id');
        $this->assertNotEmpty($headId);

        $this->deleteJson("/api/v1/templates/{$tid}/versions/{$headId}", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('template_reviewers', [
            'template_id' => $tid,
            'user_id' => $reviewerTemplateLive,
            'stage' => 1,
        ]);
        $this->assertDatabaseHas('template_document_reviewers', [
            'template_id' => $tid,
            'user_id' => $reviewerDocumentLive,
        ]);
    }

    public function test_template_version_block_layers_resolve_equal_blocks_snapshot_after_second_publish(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId, []);
        $this->assignUserPermissions($creatorId, ['template.show', 'template.create']);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'T',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $bid,
            'template_id' => $tid,
            'title' => 'B',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)->assertOk();

        $this->postJson("/api/v1/templates/{$tid}/new-version", [], $headers)->assertOk();

        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'Segunda versi?n'], $headers)->assertOk();

        $tv2 = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 2)
            ->firstOrFail();

        $resolver = app(TemplateVersionBlockLayerResolver::class);
        $fromLayers = $resolver->resolveBlocksSnapshot((string) $tv2->id);

        $this->assertEquals($tv2->blocksSnapshotRows(), $fromLayers);

        $inheritsCount = DB::table('template_version_block_layers')
            ->where('entity_version_id', $tv2->id)
            ->where('inherits_from_previous_publication', true)
            ->count();

        $this->assertGreaterThan(0, $inheritsCount);
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
        ]);

        $this->deleteJson("/api/v1/templates/{$tid}", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $head = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        $data = $head->snapshot_data ?? [];
        $this->assertSame('archived', data_get($data, TemplateHeadSnapshot::JSON_TEMPLATE_KEY.'.status'));
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->deleteJson("/api/v1/templates/{$tid}", [], $headers)
            ->assertNoContent();

        $this->assertSoftDeleted('templates', ['id' => $tid]);
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

        \Illuminate\Support\Facades\DB::table('team_members')->insert([
            'id'      => (string) Str::uuid(),
            'team_id' => $gid,
            'user_id' => $userId,
            'role'    => 'member',
        ]);

        $this->postJson('/api/v1/templates', [
            'name' => 'Por grupo',
            'visibility_level' => TemplateVisibilityLevel::Team->value,
            'team_id' => $gid,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher']);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertNotFound();
    }

    public function test_creator_can_view_own_template_without_templates_read_permission(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId, []);
        DB::table('user_resolved_permissions')
            ->where('user_id', $creatorId)
            ->whereIn('permission_slug', ['template.show', 'document.create'])
            ->delete();

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Borrador propio sin read',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->getJson("/api/v1/templates/{$tid}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $tid)
            ->assertJsonPath('data.created_by', $creatorId);
    }

    public function test_index_keeps_creator_id_when_head_snapshot_missing_created_by(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Borrador con snapshot legacy',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $head = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 0)
            ->firstOrFail();
        /** @var array<string, mixed> $snapshotData */
        $snapshotData = is_array($head->snapshot_data) ? $head->snapshot_data : [];
        $templateData = isset($snapshotData['template']) && is_array($snapshotData['template'])
            ? $snapshotData['template']
            : [];
        unset($templateData['created_by']);
        $snapshotData['template'] = $templateData;
        $head->update(['snapshot_data' => $snapshotData]);

        EntityVersion::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $creatorId,
            'published_at' => now(),
            'changelog' => 'v1',
            'snapshot_data' => [
                'template' => [
                    'id' => $tid,
                    'process_id' => '00000000-0000-0000-0000-000000000001',
                    'name' => 'Publicado',
                    'created_by' => $creatorId,
                    'status' => 'published',
                    'version' => 1,
                ],
                'blocks' => [],
            ],
            'is_snapshot_immutable' => true,
        ]);

        $this->getJson('/api/v1/templates', $headers)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $tid,
                'status' => 'draft',
                'created_by' => $creatorId,
            ]);
    }

    public function test_teacher_sees_study_scoped_template_when_user_has_study_in_bd(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $stud = $this->anyStudyId();

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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        \Illuminate\Support\Facades\DB::table('user_studies')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $userB,
            'study_id' => $stud,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headersB = $this->authHeaders($userB, ['teacher']);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertOk();
    }

    public function test_teacher_sees_study_scoped_template_when_user_study_matches(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $stud = $this->anyStudyId();

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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        \Illuminate\Support\Facades\DB::table('user_studies')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $userB,
            'study_id' => $stud,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headersB = $this->authHeaders($userB, ['teacher']);

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

    public function test_assigned_reviewer_can_view_in_review_template_without_academic_context(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc';
        $studyId = $this->anyStudyId();
        $tid = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla in review sin contexto',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => $studyId,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'in_review',
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        $this->seedTemplateReviewer($tid, $reviewerId);

        $this->assertDatabaseMissing('user_studies', [
            'user_id' => $reviewerId,
            'study_id' => $studyId,
        ]);

        $headersReviewer = $this->authHeaders($reviewerId);

        $this->getJson("/api/v1/templates/{$tid}", $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.id', $tid)
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_template_first_publish_in_review_autofills_changelog_when_missing(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headersReviewer = $this->authHeaders($reviewerId, []);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'En revisi?n',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'in_review',
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
        TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $reviewerId,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $versionRow = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 1)
            ->first();
        $this->assertNotNull($versionRow);
        $this->assertSame("Publicaci\u{00f3}n autom\u{00e1}tica", (string) $versionRow->changelog);
    }

    public function test_template_creator_can_publish_draft_without_reviewers_and_autofills_default_changelog(): void
    {
        $creatorId = (string) Str::uuid();
        $headersCreator = $this->authHeaders($creatorId, []);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Draft directo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
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

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headersCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 1);

        $versionRow = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 1)
            ->where('published_by', $creatorId)
            ->first();
        $this->assertNotNull($versionRow);
        $this->assertSame("Publicaci\u{00f3}n autom\u{00e1}tica", (string) $versionRow->changelog);
    }

    public function test_template_creator_can_publish_draft_without_reviewers_even_if_not_personal(): void
    {
        $creatorId = (string) Str::uuid();
        $headersCreator = $this->authHeaders($creatorId, []);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Draft directo global',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
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

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headersCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 1);
    }

    public function test_template_publish_fails_without_blocks(): void
    {
        $creatorId = (string) Str::uuid();
        $headersCreator = $this->authHeaders($creatorId, []);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Sin bloques',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headersCreator)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'La plantilla debe tener al menos un bloque antes de publicarse.');
    }

    public function test_template_publish_requires_changelog_from_second_version_onward(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headersReviewer = $this->authHeaders($reviewerId, []);

        $tid = (string) Str::uuid();
        $bid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'En revisi?n v2',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'in_review',
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

        // Existe versi?n previa publicada -> pr?ximo publish ser? v2 y requiere changelog.
        EntityVersion::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $creatorId,
            'published_at' => now(),
            'changelog' => 'Versi?n inicial',
            'snapshot_data' => ['blocks' => []],
            'is_snapshot_immutable' => true,
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => $bid,
            'template_id' => $tid,
            'title' => 'T',
            'default_content' => ['text' => 'editable content'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->seedTemplateReviewer($tid, $reviewerId);
        TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $reviewerId,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->postJson("/api/v1/templates/{$tid}/publish", [
            'changelog' => 'Primera publicaci?n',
        ], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('entity_versions', [
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'status' => 'published',
            'published_by' => $reviewerId,
            'is_snapshot_immutable' => 1,
        ]);
        $entityVersion = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 1)
            ->first();
        $this->assertNotNull($entityVersion);
        $snapshot = is_string($entityVersion->snapshot_data)
            ? json_decode($entityVersion->snapshot_data, true)
            : $entityVersion->snapshot_data;
        $this->assertIsArray($snapshot);
        $this->assertSame($reviewerId, $snapshot['reviewers']['template_reviewers'][0]['user_id'] ?? null);
        $this->assertSame(1, $snapshot['reviewers']['template_reviewers'][0]['stage'] ?? null);
        $this->assertSame('pending', $snapshot['reviewers']['template_reviewers'][0]['status'] ?? null);
        $this->assertSame($reviewerId, $snapshot['reviewers']['document_reviewers'][0]['user_id'] ?? null);

        $vid = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 1)
            ->value('id');
        $this->assertNotEmpty($vid);

        $this->getJson("/api/v1/templates/{$tid}/versions", $headersCreator)
            ->assertOk()
            ->assertJsonCount(0, 'data');

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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $vid = (string) Str::uuid();
        EntityVersion::query()->forceCreate([
            'id' => $vid,
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'x',
            'snapshot_data' => ['blocks' => []],
            'is_snapshot_immutable' => true,
        ]);

        $version = EntityVersion::query()->findOrFail($vid);
        $this->expectException(AuthorizationException::class);
        $version->update(['changelog' => 'hack']);
    }

    public function test_template_versions_endpoint_returns_canonical_entity_versions(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Historial prioridad entity',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'entity-changelog',
            'snapshot_data' => json_encode(['template' => ['id' => $tid]], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/templates/{$tid}/versions", $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_template_versions_endpoint_keeps_latest_published_when_current_is_draft(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Historial con borrador activo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'ultima publicada visible',
            'snapshot_data' => json_encode(['template' => ['id' => $tid]], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/templates/{$tid}/versions", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version_number', 1);
    }

    public function test_template_versions_endpoint_separates_template_history_from_document_history(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Historial separado por tipo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        DB::table('entity_versions')->insert([
            [
                'id' => (string) Str::uuid(),
                'versionable_type' => Template::class,
                'versionable_id' => $tid,
                'version_number' => 1,
                'base_version_id' => null,
                'change_set' => null,
                'status' => 'published',
                'created_by' => $userId,
                'published_by' => $userId,
                'published_at' => now(),
                'changelog' => 'template-only-history',
                'snapshot_data' => json_encode(['template' => ['id' => $tid]], JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'versionable_type' => Document::class,
                'versionable_id' => $tid,
                'version_number' => 1,
                'base_version_id' => null,
                'change_set' => null,
                'status' => 'published',
                'created_by' => $userId,
                'published_by' => $userId,
                'published_at' => now(),
                'changelog' => 'document-history-should-not-appear',
                'snapshot_data' => json_encode(['document' => ['id' => $tid]], JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson("/api/v1/templates/{$tid}/versions", $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_template_versions_endpoint_lists_publications_from_entity_versions_only(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Historial canonical template',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'canonical-template-history',
            'snapshot_data' => json_encode(['blocks' => []], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/templates/{$tid}/versions", $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_template_version_detail_endpoint_accepts_entity_version_id_for_template(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        $blockId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Detalle entity version',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $entityVersionId = (string) Str::uuid();
        DB::table('entity_versions')->insert([
            'id' => $entityVersionId,
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'entity-template-detail',
            'snapshot_data' => json_encode([
                'template' => ['id' => $tid],
                'blocks' => [
                    [
                        'id' => $blockId,
                        'title' => 'Bloque entidad',
                        'default_content' => ['k' => 'v'],
                        'block_state' => 'editable',
                        'sort_order' => 0,
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/template-versions/{$entityVersionId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $entityVersionId)
            ->assertJsonPath('data.template_id', $tid)
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.changelog', 'entity-template-detail')
            ->assertJsonPath('data.blocks_snapshot.0.id', $blockId);
    }

    public function test_template_version_detail_endpoint_rejects_entity_version_of_other_type(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Detalle rechaza otro tipo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $otherEntityVersionId = (string) Str::uuid();
        DB::table('entity_versions')->insert([
            'id' => $otherEntityVersionId,
            'versionable_type' => Document::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'document-version',
            'snapshot_data' => json_encode(['document' => ['id' => $tid]], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/template-versions/{$otherEntityVersionId}", $headers)->assertNotFound();
    }

    public function test_template_v2_entity_snapshot_inherits_reviewers_when_not_changed(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();
        $docReviewerId = (string) Str::uuid();
        $this->assignUserPermissions($reviewerId, ['template.review', 'document.review']);
        $this->assignUserPermissions($docReviewerId, ['document.review']);
        [$headersCreator, $headersReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
            [],
        );

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Herencia reviewers v2',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'title' => 'Bloque base',
            'default_content' => ['k' => 'v'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);
        $this->seedTemplateReviewer($tid, $reviewerId);
        TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $docReviewerId,
        ]);

        // Publicacion v1
        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'Versi?n inicial'], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        // Simula inicio de nuevo ciclo de versionado desde la ultima publicada.
        $this->setHeadTemplateStatusDraft($tid);

        // Publicacion v2 sin tocar revisores/validadores.
        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'Versi?n 2 sin cambios'], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $v2Snapshot = $this->getTemplateEntityVersionSnapshot($tid, 2);
        $this->assertSame(
            [$reviewerId],
            array_column($v2Snapshot['reviewers']['template_reviewers'] ?? [], 'user_id')
        );
        $this->assertSame(
            [1],
            array_column($v2Snapshot['reviewers']['template_reviewers'] ?? [], 'stage')
        );
        $this->assertSame(
            ['pending'],
            array_column($v2Snapshot['reviewers']['template_reviewers'] ?? [], 'status')
        );
        $this->assertSame(
            [$docReviewerId],
            array_column($v2Snapshot['reviewers']['document_reviewers'] ?? [], 'user_id')
        );
        $this->assertDatabaseHas('entity_versions', [
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 2,
            'status' => 'published',
            'is_snapshot_immutable' => 1,
        ]);
    }

    public function test_template_v2_entity_snapshot_reflects_reviewer_changes_before_publish(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerV1Id = (string) Str::uuid();
        $reviewerV2Id = (string) Str::uuid();
        $docReviewerV2Id = (string) Str::uuid();
        User::query()->forceCreate([
            'id' => $reviewerV2Id,
            'name' => 'Reviewer V2',
            'email' => "reviewer-v2-{$reviewerV2Id}@example.test",
        ]);
        User::query()->forceCreate([
            'id' => $docReviewerV2Id,
            'name' => 'Doc Reviewer V2',
            'email' => "doc-reviewer-v2-{$docReviewerV2Id}@example.test",
        ]);
        $this->assignUserPermissions($reviewerV1Id, ['template.review', 'document.review']);
        $this->assignUserPermissions($reviewerV2Id, ['template.review', 'document.review']);
        $this->assignUserPermissions($docReviewerV2Id, ['document.review']);
        [$headersCreator, $headersReviewerV1] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerV1Id,
            [],
        );

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Cambio reviewers v2',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'title' => 'Bloque base',
            'default_content' => ['k' => 'v'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);
        $this->seedTemplateReviewer($tid, $reviewerV1Id);
        TemplateDocumentReviewer::query()->forceCreate([
            'template_id' => $tid,
            'user_id' => $reviewerV1Id,
        ]);

        // Publicacion v1 con configuracion inicial.
        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'Versi?n inicial'], $headersReviewerV1)
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        // Simula inicio de nuevo ciclo de versionado desde la ultima publicada.
        $this->setHeadTemplateStatusDraft($tid);

        // Cambia reviewers de plantilla y de documentos antes de publicar v2.
        $this->postJson("/api/v1/templates/{$tid}/reviewers", [
            'user_ids' => [$reviewerV2Id],
        ], $headersCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/document-reviewers", [
            'user_ids' => [$docReviewerV2Id],
        ], $headersCreator)->assertOk();

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)->assertOk();
        $headersReviewerV2 = $this->authHeaders($reviewerV2Id);
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'Versi?n 2 con cambios'], $headersReviewerV2)
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $v2Snapshot = $this->getTemplateEntityVersionSnapshot($tid, 2);
        $this->assertSame(
            [$reviewerV2Id],
            array_column($v2Snapshot['reviewers']['template_reviewers'] ?? [], 'user_id')
        );
        $this->assertSame(
            [1],
            array_column($v2Snapshot['reviewers']['template_reviewers'] ?? [], 'stage')
        );
        $this->assertSame(
            ['pending'],
            array_column($v2Snapshot['reviewers']['template_reviewers'] ?? [], 'status')
        );
        $this->assertSame(
            [$docReviewerV2Id],
            array_column($v2Snapshot['reviewers']['document_reviewers'] ?? [], 'user_id')
        );
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $vid = (string) Str::uuid();
        EntityVersion::query()->forceCreate([
            'id' => $vid,
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'x',
            'snapshot_data' => ['blocks' => []],
            'is_snapshot_immutable' => true,
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'title' => 'Bloque rechazo',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->seedTemplateReviewer($tid, $reviewerId);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headersCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/reject-review", [], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame(
            0,
            (int) DB::table('entity_versions')
                ->where('versionable_type', Template::class)
                ->where('versionable_id', $tid)
                ->where('version_number', '>', 0)
                ->count(),
        );
    }

    public function test_creator_can_approve_when_assigned_and_has_templates_review_permission(): void
    {
        $creatorId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headers = $this->authHeaders($creatorId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Aprobaci?n creador asignado',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'title' => 'Bloque aprobaci?n',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->seedTemplateReviewer($tid, $creatorId);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headers)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/approve-review", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->patchJson("/api/v1/templates/{$tid}", ['status' => 'published'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_sync_template_reviewers_allows_creator_included(): void
    {
        $creatorId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $otherId = '2ead4bf3-574c-41b4-95ca-cac7daed0664';
        $headers = $this->authHeaders($creatorId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Draft revisores',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->postJson("/api/v1/templates/{$tid}/reviewers", [
            'user_ids' => [$creatorId, $otherId],
        ], $headers)
            ->assertOk();
    }

    public function test_sync_template_reviewers_requires_templates_review_permission(): void
    {
        $creatorId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headers = $this->authHeaders($creatorId);
        $userWithoutPermission = (string) Str::uuid();
        User::query()->forceCreate([
            'id' => $userWithoutPermission,
            'name' => 'Sin permiso templates.review',
            'email' => "sin-perm-tpl-{$userWithoutPermission}@example.test",
        ]);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Draft revisores sin permiso',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->postJson("/api/v1/templates/{$tid}/reviewers", [
            'user_ids' => [$userWithoutPermission],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    }

    public function test_sync_template_document_reviewers_allows_creator_included(): void
    {
        $creatorId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $otherId = '2ead4bf3-574c-41b4-95ca-cac7daed0664';
        $headers = $this->authHeaders($creatorId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Draft validadores doc',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        $this->postJson("/api/v1/templates/{$tid}/document-reviewers", [
            'user_ids' => [$otherId, $creatorId],
        ], $headers)
            ->assertOk();
    }

    public function test_sync_template_document_reviewers_requires_documents_review_permission(): void
    {
        $creatorId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $headers = $this->authHeaders($creatorId);
        $userWithoutPermission = (string) Str::uuid();
        User::query()->forceCreate([
            'id' => $userWithoutPermission,
            'name' => 'Sin permiso documents.review',
            'email' => "sin-perm-doc-{$userWithoutPermission}@example.test",
        ]);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Draft validadores doc sin permiso',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        $this->postJson("/api/v1/templates/{$tid}/document-reviewers", [
            'user_ids' => [$userWithoutPermission],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    }
}
