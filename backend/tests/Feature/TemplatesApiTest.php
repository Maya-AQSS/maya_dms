<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Template;
use App\Models\TemplateBlock;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class TemplatesApiTest extends TestCase
{
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'               => 'test-issuer',
            'auth.jwt_audience'             => 'test-audience',
            'auth.template_shared_visibility_roles' => ['department-head', 'director'],
        ]);

        Cache::flush();
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

    public function test_user_can_crud_personal_template_via_api(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $create = $this->postJson('/api/v1/templates', [
            'name'        => 'Plantilla personal',
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
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['teacher']);

        $this->postJson('/api/v1/templates', [
            'name'              => 'Global prohibida',
            'visibility_level'  => TemplateVisibilityLevel::Global->value,
        ], $headers)->assertForbidden();
    }

    public function test_department_head_can_create_global_template(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['department-head']);

        $this->postJson('/api/v1/templates', [
            'name'             => 'Plantilla global',
            'visibility_level' => TemplateVisibilityLevel::Global->value,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.visibility_level', TemplateVisibilityLevel::Global->value);
    }

    public function test_index_filters_by_status_and_visibility_and_respects_per_page_max(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $t1 = (string) Str::uuid();
        $t2 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'                => $t1,
            'name'              => 'A',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => 'org-x',
            'created_by'        => $userId,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        Template::query()->forceCreate([
            'id'                => $t2,
            'name'              => 'B',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => 'org-x',
            'created_by'        => $userId,
            'status'            => 'published',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
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
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['department-head']);

        $this->postJson('/api/v1/templates', [
            'name'             => 'Sin estudio',
            'visibility_level' => TemplateVisibilityLevel::Study->value,
        ], $headers)->assertUnprocessable();
    }

    public function test_creator_cannot_change_visibility_to_shared_without_role(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['teacher']);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Mía',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => null,
            'created_by'        => $userId,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        $this->patchJson("/api/v1/templates/{$tid}", [
            'visibility_level' => TemplateVisibilityLevel::Global->value,
        ], $headers)->assertForbidden();
    }

    public function test_clone_creates_draft_personal_copy_with_suffix_and_blocks(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Original',
            'description'       => 'D',
            'visibility_level'  => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => 'org-1',
            'created_by'        => $userId,
            'status'            => 'published',
            'version'           => 2,
            'review_stages'     => 1,
            'review_mode'       => 'parallel',
        ]);

        $b1 = (string) Str::uuid();
        $b2 = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id'              => $b1,
            'template_id'     => $tid,
            'type'            => 'paragraph',
            'title'           => 'B1',
            'default_content' => ['x' => 1],
            'block_state'     => 'editable',
            'mandatory'       => true,
            'sort_order'      => 0,
        ]);
        TemplateBlock::query()->forceCreate([
            'id'              => $b2,
            'template_id'     => $tid,
            'type'            => 'heading',
            'title'           => 'B2',
            'default_content' => null,
            'block_state'     => 'locked',
            'mandatory'       => false,
            'sort_order'      => 1,
        ]);

        $response = $this->postJson("/api/v1/templates/{$tid}/clone", [], $headers);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Original (copia)')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.visibility_level', TemplateVisibilityLevel::Personal->value)
            ->assertJsonPath('data.review_stages', 1)
            ->assertJsonPath('data.review_mode', 'parallel');

        $copyId = $response->json('data.id');
        $this->assertNotSame($tid, $copyId);
        $this->assertSame(2, TemplateBlock::query()->where('template_id', $copyId)->count());
    }

    public function test_destroy_archives_when_documents_exist(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        $did = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Con docs',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => 'org-1',
            'created_by'        => $userId,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id'               => $did,
            'template_id'      => $tid,
            'title'            => 'Doc',
            'organization_id'  => 'org-1',
            'study_id'         => null,
            'created_by'       => $userId,
            'owner_id'         => $userId,
            'status'           => 'draft',
            'current_version'  => 1,
            'submitted_at'     => null,
            'published_at'     => null,
        ]);

        $this->deleteJson("/api/v1/templates/{$tid}", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('templates', [
            'id'     => $tid,
            'status' => 'archived',
        ]);
    }

    public function test_destroy_no_content_when_no_documents(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Sin docs',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => null,
            'created_by'        => $userId,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        $this->deleteJson("/api/v1/templates/{$tid}", [], $headers)
            ->assertNoContent();

        $this->assertDatabaseMissing('templates', ['id' => $tid]);
    }

    public function test_group_visibility_requires_existing_group(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['director']);

        $gid = (string) Str::uuid();
        Group::query()->forceCreate([
            'id'          => $gid,
            'name'        => 'G',
            'description' => null,
            'owner_id'    => $userId,
        ]);

        $this->postJson('/api/v1/templates', [
            'name'             => 'Por grupo',
            'visibility_level' => TemplateVisibilityLevel::Group->value,
            'group_id'         => $gid,
        ], $headers)->assertCreated()->assertJsonPath('data.group_id', $gid);
    }

    public function test_peer_can_view_global_template_in_same_organization(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $org   = 'org-same';

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Global compartida',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Global->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => $org,
            'created_by'        => $userA,
            'status'            => 'published',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher'], ['organization_id' => $org]);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)
            ->assertOk()
            ->assertJsonPath('data.id', $tid);
    }

    public function test_peer_cannot_view_others_personal_template_even_same_organization(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $org   = 'org-same';

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Personal ajena',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => $org,
            'created_by'        => $userA,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher'], ['organization_id' => $org]);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertNotFound();
    }

    public function test_teacher_sees_study_scoped_template_when_jwt_contains_study_id(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $org   = 'org-stud';
        $stud  = 'study-xyz';

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Por estudio',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => $stud,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => $org,
            'created_by'        => $userA,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher'], [
            'organization_id' => $org,
            'study_id'        => $stud,
        ]);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertOk();
    }

    public function test_teacher_does_not_see_study_template_from_other_organization(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $stud  = 'study-abc';

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'Otro tenant',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Study->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => $stud,
            'module_id'         => null,
            'group_id'          => null,
            'organization_id'   => 'org-a',
            'created_by'        => $userA,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher'], [
            'organization_id' => 'org-b',
            'study_id'        => $stud,
        ]);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertNotFound();
    }

    public function test_teacher_sees_group_template_when_member(): void
    {
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();
        $gid   = (string) Str::uuid();

        Group::query()->forceCreate([
            'id'          => $gid,
            'name'        => 'Curso',
            'description' => null,
            'owner_id'    => $userA,
        ]);

        GroupMember::query()->forceCreate([
            'id'       => (string) Str::uuid(),
            'group_id' => $gid,
            'user_id'  => $userB,
            'role'     => 'member',
        ]);

        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'                => $tid,
            'name'              => 'De grupo',
            'description'       => null,
            'visibility_level'  => TemplateVisibilityLevel::Group->value,
            'delivery_deadline' => null,
            'study_type_id'     => null,
            'study_id'          => null,
            'module_id'         => null,
            'group_id'          => $gid,
            'organization_id'   => null,
            'created_by'        => $userA,
            'status'            => 'draft',
            'version'           => 1,
            'review_stages'     => 0,
            'review_mode'       => 'sequential',
        ]);

        $headersB = $this->authHeaders($userB, ['teacher']);

        $this->getJson("/api/v1/templates/{$tid}", $headersB)->assertOk();
    }
}
