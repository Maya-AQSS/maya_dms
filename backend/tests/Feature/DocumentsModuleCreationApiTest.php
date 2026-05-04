<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateVersion;
use Database\Seeders\PermissionsSeeder;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class DocumentsModuleCreationApiTest extends TestCase
{
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

        $this->seed(PermissionsSeeder::class);
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

    /**
     * @param  array<string, mixed>  $extraClaims
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $extraClaims = []): array
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
            [],
            $extraClaims,
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    private function seedAcademicHierarchy(string $moduleId = 'MOD-1', string $studyId = 'STUDY-1'): void
    {
        DB::table('study_types')->insert([
            'id' => 'TYPE-1',
            'name' => 'Bachillerato',
        ]);

        DB::table('studies')->insert([
            'id' => $studyId,
            'study_type_id' => 'TYPE-1',
            'name' => '1º Bachillerato',
        ]);

        DB::table('course_modules')->insert([
            'id' => $moduleId,
            'study_id' => $studyId,
            'name' => 'Matemáticas I',
        ]);
    }

    private function createPublishedTemplateWithVersion(
        string $templateId,
        string $creatorId,
        string $moduleId,
        string $name,
        ?string $description = null,
    ): TemplateVersion {
        $templateBlockId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => $name,
            'description' => $description,
            'visibility_level' => TemplateVisibilityLevel::Module->value,
            'delivery_deadline' => null,
            'study_type_id' => 'TYPE-1',
            'study_id' => 'STUDY-1',
            'module_id' => $moduleId,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        DB::table('template_blocks')->insert([
            'id' => $templateBlockId,
            'template_id' => $templateId,
            'title' => 'Objetivos',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TemplateVersion::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'version_number' => 1,
            'blocks_snapshot' => [[
                'id' => $templateBlockId,
                'title' => 'Objetivos',
                'default_content' => null,
                'block_state' => 'editable',
                'sort_order' => 1,
            ]],
            'changelog' => 'Versión inicial',
            'published_by' => $creatorId,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_creation_options_returns_none_when_module_has_no_published_templates(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);
        $this->seedAcademicHierarchy();

        $this->getJson('/api/v1/documents/creation-options?module_id=MOD-1', $headers)
            ->assertOk()
            ->assertJsonPath('data.can_create', false)
            ->assertJsonPath('data.mode', 'none')
            ->assertJsonCount(0, 'data.options');
    }

    public function test_creation_options_returns_auto_when_only_one_template_is_available(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);
        $this->seedAcademicHierarchy();

        $version = $this->createPublishedTemplateWithVersion(
            templateId: (string) Str::uuid(),
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla Única',
            description: 'Descripción breve',
        );

        $this->getJson('/api/v1/documents/creation-options?module_id=MOD-1', $headers)
            ->assertOk()
            ->assertJsonPath('data.can_create', true)
            ->assertJsonPath('data.mode', 'auto')
            ->assertJsonPath('data.options.0.template_version_id', $version->id)
            ->assertJsonPath('data.options.0.name', 'Plantilla Única');
    }

    public function test_creation_options_returns_select_when_multiple_templates_are_available(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);
        $this->seedAcademicHierarchy();

        $this->createPublishedTemplateWithVersion(
            templateId: (string) Str::uuid(),
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla A',
        );

        $this->createPublishedTemplateWithVersion(
            templateId: (string) Str::uuid(),
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla B',
        );

        $this->getJson('/api/v1/documents/creation-options?module_id=MOD-1', $headers)
            ->assertOk()
            ->assertJsonPath('data.can_create', true)
            ->assertJsonPath('data.mode', 'select')
            ->assertJsonCount(2, 'data.options');
    }

    public function test_create_from_module_uses_sub_claim_as_creator_and_owner(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);
        $this->seedAcademicHierarchy();

        $version = $this->createPublishedTemplateWithVersion(
            templateId: (string) Str::uuid(),
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla Única',
        );

        $response = $this->postJson('/api/v1/documents/create-from-module', [
            'module_id' => 'MOD-1',
            'template_version_id' => $version->id,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by', $userId)
            ->assertJsonPath('data.owner_id', $userId)
            ->assertJsonPath('data.module_id', 'MOD-1')
            ->assertJsonPath('data.study_type_id', 'TYPE-1')
            ->assertJsonPath('data.study_id', 'STUDY-1');

        $this->assertDatabaseHas('documents', [
            'id' => $response->json('data.id'),
            'created_by' => $userId,
            'owner_id' => $userId,
            'template_version_id' => $version->id,
            'study_type_id' => 'TYPE-1',
            'module_id' => 'MOD-1',
            'status' => 'draft',
        ]);
    }

    public function test_create_from_module_requires_template_version_when_multiple_options_exist(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);
        $this->seedAcademicHierarchy();

        $this->createPublishedTemplateWithVersion(
            templateId: (string) Str::uuid(),
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla A',
        );

        $this->createPublishedTemplateWithVersion(
            templateId: (string) Str::uuid(),
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla B',
        );

        $this->postJson('/api/v1/documents/create-from-module', [
            'module_id' => 'MOD-1',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['template_version_id']);
    }

    public function test_new_document_appears_first_in_documents_list(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);
        $this->seedAcademicHierarchy();

        $version = $this->createPublishedTemplateWithVersion(
            templateId: (string) Str::uuid(),
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla Única',
        );

        $first = $this->postJson('/api/v1/documents/create-from-module', [
            'module_id' => 'MOD-1',
            'template_version_id' => $version->id,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)->assertCreated();

        // Forzamos antigüedad para validar orden sin depender del reloj del sistema.
        DB::table('documents')
            ->where('id', $first->json('data.id'))
            ->update(['created_at' => now()->subMinute()]);

        $second = $this->postJson('/api/v1/documents/create-from-module', [
            'module_id' => 'MOD-1',
            'template_version_id' => $version->id,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)->assertCreated();

        $list = $this->getJson('/api/v1/documents', $headers)
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($list);
        $this->assertSame($second->json('data.id'), $list[0]['id']);
    }
}

