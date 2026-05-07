<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Support\DocumentHeadSnapshot;
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

    /**
     * @return string Id en {@see entity_versions} de la publicación.
     */
    private function createPublishedTemplateWithVersion(
        string $templateId,
        string $creatorId,
        string $moduleId,
        string $name,
        ?string $description = null,
    ): string {
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

        $entityVersionId = (string) Str::uuid();
        $publishedAt = now();

        DB::table('entity_versions')->insert([
            'id' => $entityVersionId,
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $creatorId,
            'published_at' => $publishedAt,
            'changelog' => 'Versión inicial',
            'snapshot_data' => json_encode([
                'blocks' => [[
                    'id' => $templateBlockId,
                    'title' => 'Objetivos',
                    'default_content' => null,
                    'block_state' => 'editable',
                    'sort_order' => 1,
                    'type' => '',
                    'mandatory' => false,
                ]],
            ]),
            'is_snapshot_immutable' => true,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ]);

        return $entityVersionId;
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
            ->assertJsonPath('data.options.0.template_version_id', $version)
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

    public function test_creation_options_keeps_published_template_when_live_head_is_draft(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId);
        $headers = $this->authHeaders($userId);
        $this->seedAcademicHierarchy();

        $templateId = (string) Str::uuid();
        $version = $this->createPublishedTemplateWithVersion(
            templateId: $templateId,
            creatorId: $userId,
            moduleId: 'MOD-1',
            name: 'Plantilla con v1 publicada',
        );

        $template = Template::query()->withoutGlobalScopes()->findOrFail($templateId);
        $template->update(['status' => 'draft']);

        $this->getJson('/api/v1/documents/creation-options?module_id=MOD-1', $headers)
            ->assertOk()
            ->assertJsonPath('data.can_create', true)
            ->assertJsonPath('data.options.0.template_id', $templateId)
            ->assertJsonPath('data.options.0.template_version_id', $version);
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
            'template_version_id' => $version,
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

        $docId = (string) $response->json('data.id');
        $this->assertDatabaseHas('documents', [
            'id' => $docId,
            'template_version_id' => $version,
        ]);

        $head = EntityVersion::query()
            ->where('versionable_type', Document::class)
            ->where('versionable_id', $docId)
            ->where('version_number', 0)
            ->firstOrFail();
        $this->assertSame('draft', $head->status);
        $this->assertSame($userId, (string) data_get($head->snapshot_data, DocumentHeadSnapshot::JSON_DOCUMENT_KEY.'.created_by'));
        $this->assertSame($userId, (string) data_get($head->snapshot_data, DocumentHeadSnapshot::JSON_DOCUMENT_KEY.'.owner_id'));
        $this->assertSame('TYPE-1', (string) data_get($head->snapshot_data, DocumentHeadSnapshot::JSON_DOCUMENT_KEY.'.study_type_id'));
        $this->assertSame('MOD-1', (string) data_get($head->snapshot_data, DocumentHeadSnapshot::JSON_DOCUMENT_KEY.'.module_id'));
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
            'template_version_id' => $version,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)->assertCreated();

        // Forzamos antigüedad para validar orden sin depender del reloj del sistema.
        DB::table('documents')
            ->where('id', $first->json('data.id'))
            ->update(['created_at' => now()->subMinute()]);

        $second = $this->postJson('/api/v1/documents/create-from-module', [
            'module_id' => 'MOD-1',
            'template_version_id' => $version,
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

