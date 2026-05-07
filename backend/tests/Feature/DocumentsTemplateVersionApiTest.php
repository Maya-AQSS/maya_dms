<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\DocumentVersion;
use App\Models\DocumentShare;
use App\Services\DocumentVersionBlockLayerResolver;
use App\Support\DocumentHeadSnapshot;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateReviewer;
use Illuminate\Auth\Access\AuthorizationException;
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

    private function setDocumentHeadWorkingStatus(string $docId, string $status): void
    {
        $ev = EntityVersion::query()
            ->where('versionable_type', Document::class)
            ->where('versionable_id', $docId)
            ->where('version_number', 0)
            ->firstOrFail();
        $merged = DocumentHeadSnapshot::mergeDocumentKey($ev->snapshot_data ?? [], ['status' => $status]);
        $ev->update([
            'status' => $status,
            'snapshot_data' => $merged,
        ]);
    }

    private function anyStudyId(): string
    {
        $existing = DB::table('studies')->value('id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $studyTypeId = (string) Str::uuid();
        $studyId = (string) Str::uuid();

        DB::table('study_types')->insertOrIgnore([
            'id' => $studyTypeId,
            'name' => 'Tipo test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('studies')->insertOrIgnore([
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Mi doc',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_store_document_without_template_version_id_anchors_to_highest_published_entity_version(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla con v1 publicada y v2 sólo en entity_versions',
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

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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

        $now = now();
        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 2,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $reviewerId,
            'published_at' => $now,
            'changelog' => 'v2 publicación adicional',
            'snapshot_data' => json_encode([
                'blocks' => [[
                    'id' => $b1,
                    'title' => 'Bloque',
                    'default_content' => null,
                    'block_state' => 'editable',
                    'sort_order' => 0,
                ]],
            ], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $v2Id = (string) DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 2)
            ->value('id');

        $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc anclado a la publicación mayor',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)
            ->assertCreated()
            ->assertJsonPath('data.template_version_id', $v2Id)
            ->assertJsonPath('data.template_version_number', 2);
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque publicado',
            'default_content' => null,
            'block_state' => 'editable',
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

        $versionId = (string) DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 1)
            ->value('id');
        $this->assertNotNull($versionId);

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_version_id' => $versionId,
            'title' => 'Expediente',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator);

        $createDoc->assertCreated()
            ->assertJsonPath('data.template_id', $tid)
            ->assertJsonPath('data.template_version_id', $versionId);
    }

    public function test_clone_document_creates_draft_copy_with_suffix_and_blocks(): void
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
            'name' => 'Normativa clon doc',
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
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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
            'title' => 'Expediente original',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $versionId = (string) $createDoc->json('data.template_version_id');

        $clone = $this->postJson("/api/v1/documents/{$docId}/clone", [], $hCreator);
        $clone->assertCreated()
            ->assertJsonPath('data.title', 'Expediente original (copia)')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.template_id', $tid)
            ->assertJsonPath('data.template_version_id', $versionId)
            ->assertJsonPath('data.created_by', $creatorId)
            ->assertJsonPath('data.owner_id', $creatorId)
            ->assertJsonPath('data.template_version_number', 1);

        $copyId = (string) $clone->json('data.id');
        $this->assertNotSame($docId, $copyId);
        $clone->assertJsonCount(1, 'data.blocks');
    }

    public function test_clone_document_materializes_from_latest_published_snapshot_when_live_blocks_differ(): void
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
            'name' => 'Plantilla clon snapshot',
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

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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
            'title' => 'Título al publicar',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $documentBlockId = (string) $createDoc->json('data.blocks.0.document_block_id');

        $snapshotParagraph = 'Texto congelado en publicación';
        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [
                ['type' => 'paragraph', 'content' => $snapshotParagraph],
            ],
        ], $hCreator)->assertOk();

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)->assertOk();
        DB::table('document_reviews')->where('document_id', $docId)->delete();

        $this->postJson("/api/v1/documents/{$docId}/publish", [
            'changelog' => 'Primera publicación',
        ], $hCreator)->assertOk();

        // Sin flujo productivo publicado→borrador en esta suite: se simula una nueva edición para divergir del snapshot.
        $this->setDocumentHeadWorkingStatus($docId, 'draft');

        $this->putJson("/api/v1/documents/{$docId}", [
            'title' => 'Título solo en borrador vivo',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertOk();

        $liveParagraph = 'Texto solo en borrador actual';
        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [
                ['type' => 'paragraph', 'content' => $liveParagraph],
            ],
        ], $hCreator)->assertOk();

        $clone = $this->postJson("/api/v1/documents/{$docId}/clone", [], $hCreator);
        $clone->assertCreated()
            ->assertJsonPath('data.title', 'Título al publicar (copia)');

        $copyId = (string) $clone->json('data.id');
        $this->assertNotSame($docId, $copyId);

        $showCopy = $this->getJson("/api/v1/documents/{$copyId}", $hCreator)->assertOk();
        $this->assertSame(
            $snapshotParagraph,
            data_get($showCopy->json(), 'data.blocks.0.content.0.content'),
        );
        $this->assertNotSame(
            $liveParagraph,
            data_get($showCopy->json(), 'data.blocks.0.content.0.content'),
        );
    }

    public function test_clone_document_falls_back_to_live_blocks_when_published_snapshot_has_no_usable_blocks(): void
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
            'name' => 'Plantilla clon fallback bloques',
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

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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
            'title' => 'Doc fallback bloques',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $documentBlockId = (string) $createDoc->json('data.blocks.0.document_block_id');

        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [
                ['type' => 'paragraph', 'content' => 'Texto inicial para revisión y publicación'],
            ],
        ], $hCreator)->assertOk();

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)->assertOk();
        DB::table('document_reviews')->where('document_id', $docId)->delete();

        $this->postJson("/api/v1/documents/{$docId}/publish", [
            'changelog' => 'Pub',
        ], $hCreator)->assertOk();

        $this->setDocumentHeadWorkingStatus($docId, 'draft');

        $snapshotRow = DB::table('document_versions')->where('document_id', $docId)->orderByDesc('version_number')->first();
        $this->assertNotNull($snapshotRow);
        $this->assertNotNull($snapshotRow->entity_version_id);
        $evRow = DB::table('entity_versions')->where('id', (string) $snapshotRow->entity_version_id)->first();
        $this->assertNotNull($evRow);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $evRow->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
        $decoded['blocks'] = [];
        DB::table('entity_versions')->where('id', (string) $snapshotRow->entity_version_id)->update([
            'snapshot_data' => json_encode($decoded, JSON_THROW_ON_ERROR),
        ]);

        $fallbackText = 'Contenido solo en borrador vivo';
        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [
                ['type' => 'paragraph', 'content' => $fallbackText],
            ],
        ], $hCreator)->assertOk();

        $clone = $this->postJson("/api/v1/documents/{$docId}/clone", [], $hCreator)->assertCreated();
        $copyId = (string) $clone->json('data.id');

        $showCopy = $this->getJson("/api/v1/documents/{$copyId}", $hCreator)->assertOk();
        $this->assertSame(
            $fallbackText,
            data_get($showCopy->json(), 'data.blocks.0.content.0.content'),
        );
    }

    public function test_post_document_new_version_sets_draft_when_published(): void
    {
        // Sin revisores en plantilla: submit puede publicar directo; el objetivo es tener published antes de new-version.
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId, [
            'documents.create',
            'templates.read',
            'templates.create',
            'users.search',
        ]);

        $headers = $this->authHeaders($creatorId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla doc nueva versión',
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
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Expediente nueva versión',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $documentBlockId = (string) $createDoc->json('data.blocks.0.document_block_id');

        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [
                ['type' => 'paragraph', 'content' => 'Texto para publicación automática'],
            ],
        ], $headers)->assertOk();

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $headers)->assertOk();

        $this->assertSame(
            'published',
            (string) DB::table('entity_versions')
                ->where('versionable_type', Document::class)
                ->where('versionable_id', $docId)
                ->where('version_number', 0)
                ->value('status'),
        );

        $this->postJson("/api/v1/documents/{$docId}/new-version", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame('draft', (string) DB::table('entity_versions')
            ->where('versionable_type', Document::class)
            ->where('versionable_id', $docId)
            ->where('version_number', 0)
            ->value('status'));
        $docAfterNewVersion = Document::query()->find($docId);
        $this->assertNotNull($docAfterNewVersion);
        $this->assertNull($docAfterNewVersion->published_at);
    }

    public function test_document_version_block_layers_resolve_equal_snapshot_blocks_after_second_publish(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId, [
            'documents.create',
            'templates.read',
            'templates.create',
            'users.search',
        ]);

        $headers = $this->authHeaders($creatorId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla resolver doc capas',
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
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'B',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Expediente capas',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $documentBlockId = (string) $createDoc->json('data.blocks.0.document_block_id');

        $stableParagraph = 'Contenido estable entre v1 y v2';
        $this->putJson("/api/v1/documents/{$docId}/blocks/{$documentBlockId}", [
            'content' => [
                ['type' => 'paragraph', 'content' => $stableParagraph],
            ],
        ], $headers)->assertOk();

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $headers)->assertOk();

        $this->postJson("/api/v1/documents/{$docId}/new-version", [], $headers)->assertOk();

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $headers)->assertOk();

        $dv2 = DocumentVersion::query()
            ->where('document_id', $docId)
            ->where('version_number', 2)
            ->where('trigger_event', 'published')
            ->firstOrFail();

        $resolver = app(DocumentVersionBlockLayerResolver::class);
        $fromLayers = $resolver->resolveBlocksSnapshot($dv2->id);

        $resolved = $dv2->resolvedSnapshotData();
        $blocksFromSnap = is_array($resolved) && isset($resolved['blocks']) && is_array($resolved['blocks'])
            ? array_values($resolved['blocks'])
            : [];

        $this->assertEquals($blocksFromSnap, $fromLayers);

        $inheritsCount = DB::table('document_version_block_layers')
            ->where('document_version_id', $dv2->id)
            ->where('inherits_from_previous_publication', true)
            ->count();

        $this->assertGreaterThan(0, $inheritsCount);
    }

    public function test_clone_document_returns_forbidden_for_read_only_collaborator(): void
    {
        $ownerId = (string) Str::uuid();
        $readerId = (string) Str::uuid();
        $this->grantPermissionsForUser($ownerId);
        $this->grantPermissionsForUser($readerId, ['documents.create', 'templates.read', 'users.search']);

        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hOwner, $hReviewer] = $this->authHeadersCreatorAndReviewer($ownerId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla clon 403',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $ownerId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'B',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hOwner)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $docId = (string) $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc compartido lectura',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hOwner)->assertCreated()->json('data.id');

        DocumentShare::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $docId,
            'user_id' => $readerId,
            'permission' => 'read',
            'granted_by' => $ownerId,
        ]);

        $hReader = $this->authHeaders($readerId);

        $this->postJson("/api/v1/documents/{$docId}/clone", [], $hReader)->assertForbidden();
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque publicado',
            'default_content' => null,
            'block_state' => 'editable',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator);

        $createDoc->assertCreated();
        $docId = $createDoc->json('data.id');
        $versionIdV1 = $createDoc->json('data.template_version_id');
        $this->assertNotEmpty($versionIdV1);
        $createDoc->assertJsonCount(1, 'data.blocks');
        $createDoc->assertJsonPath('data.blocks.0.title', 'Bloque publicado');
        $createDoc->assertJsonPath('data.template_version_number', 1);

        $show = $this->getJson("/api/v1/documents/{$docId}", $hCreator);
        $show->assertOk();
        $show->assertJsonPath('data.template_version_id', $versionIdV1);
        $show->assertJsonPath('data.template_version_number', 1);
        $show->assertJsonCount(1, 'data.blocks');
        $show->assertJsonPath('data.blocks.0.title', 'Bloque publicado');
        $show->assertJsonPath('data.team', null);
    }

    public function test_show_document_blocks_fall_back_to_entity_snapshot_when_legacy_blocks_snapshot_empty(): void
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
            'name' => 'Normativa entity blocks',
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
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque desde snapshot entity',
            'default_content' => null,
            'block_state' => 'editable',
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
            'title' => 'Expediente entity blocks',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $versionIdV1 = (string) $createDoc->json('data.template_version_id');

        DB::table('entity_versions')->where('id', $versionIdV1)->update([
            'snapshot_data' => json_encode(['blocks' => []], JSON_THROW_ON_ERROR),
        ]);

        $show = $this->getJson("/api/v1/documents/{$docId}", $hCreator);
        $show->assertOk();
        $show->assertJsonPath('data.template_version_number', 1);
        $show->assertJsonCount(1, 'data.blocks');
        $show->assertJsonPath('data.blocks.0.title', 'Bloque desde snapshot entity');
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'optional',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque editable',
            'default_content' => null,
            'block_state' => 'editable',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque bloqueado',
            'default_content' => null,
            'block_state' => 'locked',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'optional',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->putJson("/api/v1/documents/{$docId}", [
            'title' => 'Título actualizado',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'optional',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque obligatorio',
            'default_content' => null,
            'block_state' => 'editable',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque obligatorio',
            'default_content' => null,
            'block_state' => 'editable',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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

    public function test_submit_document_allows_empty_locked_blocks(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla locked',
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

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque locked',
            'default_content' => null,
            'block_state' => 'locked',
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
            'title' => 'Doc locked vacío',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_submit_document_uses_reviewers_from_published_template_version_snapshot(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerFromVersion = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $reviewerLiveConfig = (string) Str::uuid();
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerFromVersion);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla reviewers versionados',
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
        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'optional',
            'sort_order' => 0,
        ]);
        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerFromVersion,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        // Cambia la configuración viva de revisores tras publicar v1.
        DB::table('template_reviewers')->where('template_id', $tid)->delete();
        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerLiveConfig,
            'stage' => 1,
        ]);

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc reviewers versionados',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->assertDatabaseHas('document_reviews', [
            'document_id' => $docId,
            'reviewer_id' => $reviewerFromVersion,
            'status' => 'pending',
            'stage' => 1,
        ]);
        $this->assertDatabaseMissing('document_reviews', [
            'document_id' => $docId,
            'reviewer_id' => $reviewerLiveConfig,
            'status' => 'pending',
            'stage' => 1,
        ]);
    }

    public function test_submit_document_falls_back_to_live_reviewers_when_version_snapshot_is_missing(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerFromVersion = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $reviewerLiveConfig = (string) Str::uuid();
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerFromVersion);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla fallback reviewers',
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
        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'optional',
            'sort_order' => 0,
        ]);
        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerFromVersion,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        // Mantiene la fila canónica (FK en documentos) pero elimina revisores del snapshot para forzar fallback a config viva.
        $evRow = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 1)
            ->first();
        $this->assertNotNull($evRow);
        $snapshot = json_decode((string) $evRow->snapshot_data, true);
        $this->assertIsArray($snapshot);
        unset($snapshot['reviewers']);
        DB::table('entity_versions')->where('id', $evRow->id)->update([
            'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
        ]);

        DB::table('template_reviewers')->where('template_id', $tid)->delete();
        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerLiveConfig,
            'stage' => 1,
        ]);

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc fallback reviewers',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->assertDatabaseHas('document_reviews', [
            'document_id' => $docId,
            'reviewer_id' => $reviewerLiveConfig,
            'status' => 'pending',
            'stage' => 1,
        ]);
        $this->assertDatabaseMissing('document_reviews', [
            'document_id' => $docId,
            'reviewer_id' => $reviewerFromVersion,
            'status' => 'pending',
            'stage' => 1,
        ]);
    }

    public function test_approve_last_review_persists_published_document_version_snapshot(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        $studyId = $this->anyStudyId();
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
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'optional',
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

        DB::table('user_studies')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $reviewerId,
            'study_id' => $studyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc snapshot',
            'study_id' => $studyId,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
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
        $this->assertDatabaseHas('entity_versions', [
            'versionable_type' => Document::class,
            'versionable_id' => $docId,
            'version_number' => 1,
            'status' => 'published',
            'published_by' => $reviewerId,
            'is_snapshot_immutable' => 1,
        ]);

        $legacyDv = DocumentVersion::query()->where('document_id', $docId)->firstOrFail();
        $snapshot = $legacyDv->resolvedSnapshotData();
        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('document', $snapshot);
        $this->assertArrayHasKey('blocks', $snapshot);
        $this->assertSame($docId, $snapshot['document']['id']);
        $this->assertSame('published', $snapshot['document']['status']);
        $this->assertNotEmpty($snapshot['blocks']);

        $this->assertSame(1, (int) Document::query()->find($docId)?->current_version);

        $versionId = (string) $row->id;
        $show = $this->getJson("/api/v1/documents/{$docId}/versions/{$versionId}", $hCreator)
            ->assertOk()
            ->json('data');
        $this->assertSame($versionId, $show['id']);
        $this->assertArrayHasKey('snapshot_data', $show);
        $this->assertSame('Cierre v1 liberado', $show['changelog']);
    }

    public function test_document_versions_endpoint_prefers_entity_versions_over_legacy_table(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId, ['templates.read', 'documents.read']);
        $headers = $this->authHeaders($userId);

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Template historial documento',
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

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc historial',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'published',
        ]);

        DocumentVersion::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'version_number' => 1,
            'trigger_event' => 'published',
            'triggered_by' => $userId,
            'notes' => 'legacy-doc-changelog',
            'snapshot_data' => ['document' => ['id' => $documentId], 'blocks' => []],
            'created_at' => now(),
        ]);

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Document::class,
            'versionable_id' => $documentId,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'entity-doc-changelog',
            'snapshot_data' => json_encode(['document' => ['id' => $documentId]], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/documents/{$documentId}/versions", $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_document_version_detail_endpoint_accepts_entity_version_id_for_document(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId, ['templates.read', 'documents.read']);
        $headers = $this->authHeaders($userId);

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $entityVersionId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Template detalle entity doc',
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

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc detalle entity',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'published',
        ]);

        DB::table('entity_versions')->insert([
            'id' => $entityVersionId,
            'versionable_type' => Document::class,
            'versionable_id' => $documentId,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'entity-doc-detail',
            'snapshot_data' => json_encode(['document' => ['id' => $documentId], 'blocks' => []], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/documents/{$documentId}/versions/{$entityVersionId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $entityVersionId)
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.changelog', 'entity-doc-detail')
            ->assertJsonPath('data.snapshot_data.document.id', $documentId);
    }

    public function test_document_version_detail_endpoint_rejects_entity_version_of_other_type(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId, ['templates.read', 'documents.read']);
        $headers = $this->authHeaders($userId);

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $entityVersionId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Template detalle tipo invalido',
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

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc detalle tipo invalido',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'published',
        ]);

        DB::table('entity_versions')->insert([
            'id' => $entityVersionId,
            'versionable_type' => Template::class,
            'versionable_id' => $documentId,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
            'changelog' => 'template-wrong-type',
            'snapshot_data' => json_encode(['template' => ['id' => $documentId]], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/documents/{$documentId}/versions/{$entityVersionId}", $headers)
            ->assertNotFound();
    }

    public function test_document_versions_endpoint_separates_document_history_from_template_history(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId, ['templates.read', 'documents.read']);
        $headers = $this->authHeaders($userId);

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Template isolation',
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

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc isolation',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'published',
        ]);

        DB::table('entity_versions')->insert([
            [
                'id' => (string) Str::uuid(),
                'versionable_type' => Document::class,
                'versionable_id' => $documentId,
                'version_number' => 1,
                'base_version_id' => null,
                'change_set' => null,
                'status' => 'published',
                'created_by' => $userId,
                'published_by' => $userId,
                'published_at' => now(),
                'changelog' => 'document-only-history',
                'snapshot_data' => json_encode(['document' => ['id' => $documentId]], JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'versionable_type' => Template::class,
                'versionable_id' => $documentId,
                'version_number' => 1,
                'base_version_id' => null,
                'change_set' => null,
                'status' => 'published',
                'created_by' => $userId,
                'published_by' => $userId,
                'published_at' => now(),
                'changelog' => 'template-history-should-not-appear',
                'snapshot_data' => json_encode(['template' => ['id' => $documentId]], JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson("/api/v1/documents/{$documentId}/versions", $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_document_versions_endpoint_falls_back_to_legacy_when_entity_versions_do_not_exist(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId, ['templates.read', 'documents.read']);
        $headers = $this->authHeaders($userId);

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Template legacy doc fallback',
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

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc legacy fallback',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'published',
        ]);

        DocumentVersion::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'version_number' => 1,
            'trigger_event' => 'published',
            'triggered_by' => $userId,
            'notes' => 'legacy-doc-fallback',
            'snapshot_data' => ['document' => ['id' => $documentId], 'blocks' => []],
            'created_at' => now(),
        ]);

        $this->getJson("/api/v1/documents/{$documentId}/versions", $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'optional',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->postJson("/api/v1/documents/{$docId}/submit", [], $hCreator)
            ->assertOk();

        // Borrar revisiones pendientes para simular escenario sin revisores pendientes.
        // El titular sigue teniendo visibilidad sobre su propio documento.
        DB::table('document_reviews')->where('document_id', $docId)->delete();

        $this->postJson("/api/v1/documents/{$docId}/publish", [], $hCreator)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['changelog']);

        $this->postJson("/api/v1/documents/{$docId}/publish", [
            'changelog' => 'Publicación directa con notas',
        ], $hCreator)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertSame(
            'Publicación directa con notas',
            (string) DB::table('document_versions')->where('document_id', $docId)->value('notes'),
        );
    }

    public function test_document_version_snapshot_cannot_be_updated_via_eloquent(): void
    {
        $userId = (string) Str::uuid();
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $versionId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Doc snapshot',
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

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc snapshot',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'published',
        ]);

        DocumentVersion::query()->forceCreate([
            'id' => $versionId,
            'document_id' => $documentId,
            'version_number' => 1,
            'trigger_event' => 'published',
            'triggered_by' => $userId,
            'snapshot_data' => ['document' => ['id' => $documentId], 'blocks' => []],
            'notes' => 'v1',
            'is_immutable' => true,
            'created_at' => now(),
        ]);

        $version = DocumentVersion::query()->findOrFail($versionId);
        $this->expectException(AuthorizationException::class);
        $version->update(['notes' => 'hack']);
    }

    public function test_document_version_snapshot_mutation_via_http_returns_403(): void
    {
        $userId = (string) Str::uuid();
        $this->grantPermissionsForUser($userId, ['templates.read', 'documents.read']);
        $headers = $this->authHeaders($userId);

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $versionId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Doc snapshot HTTP',
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

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc snapshot HTTP',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'published',
        ]);

        DocumentVersion::query()->forceCreate([
            'id' => $versionId,
            'document_id' => $documentId,
            'version_number' => 1,
            'trigger_event' => 'published',
            'triggered_by' => $userId,
            'snapshot_data' => ['document' => ['id' => $documentId], 'blocks' => []],
            'notes' => 'v1',
            'is_immutable' => true,
            'created_at' => now(),
        ]);

        $this->putJson("/api/v1/documents/{$documentId}/versions/{$versionId}", ['notes' => 'hack'], $headers)->assertForbidden();
        $this->patchJson("/api/v1/documents/{$documentId}/versions/{$versionId}", ['notes' => 'hack'], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/documents/{$documentId}/versions/{$versionId}", [], $headers)->assertForbidden();
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
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $v2Id = (string) Str::uuid();
        $now = now();
        DB::table('entity_versions')->insert([
            'id' => $v2Id,
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 2,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $reviewerId,
            'published_by' => $reviewerId,
            'published_at' => $now,
            'changelog' => 'Normativa v2 publicada',
            'snapshot_data' => json_encode(['blocks' => []], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
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
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $this->getJson("/api/v1/documents/{$docId}/template-version-status", $hCreator)
            ->assertOk()
            ->assertJsonPath('data.has_update', false)
            ->assertJsonPath('data.changelog', null)
            ->assertJsonPath('data.current_version.version_number', 1)
            ->assertJsonPath('data.latest_version.version_number', 1);
    }

    public function test_template_version_status_on_version_number_tie_prefers_entity_versions_for_latest(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla empate latest',
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

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1 empate'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Doc empate latest',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');
        $legacyV1Id = (string) $createDoc->json('data.template_version_id');

        $entityV1Id = (string) DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $tid)
            ->where('version_number', 1)
            ->where('status', 'published')
            ->value('id');

        $this->assertNotSame('', $entityV1Id);
        $this->assertNotSame('', $legacyV1Id);

        $this->getJson("/api/v1/documents/{$docId}/template-version-status", $hCreator)
            ->assertOk()
            ->assertJsonPath('data.has_update', false)
            ->assertJsonPath('data.latest_version.id', $entityV1Id)
            ->assertJsonPath('data.latest_version.version_number', 1)
            ->assertJsonPath('data.current_version.id', $legacyV1Id)
            ->assertJsonPath('data.current_version.version_number', 1);
    }

    public function test_template_version_status_prefers_entity_versions_when_latest_only_published_in_entity_versions(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla entity latest',
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

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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
            'title' => 'Doc entity latest',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $entityV2Id = (string) Str::uuid();
        $now = now();
        DB::table('entity_versions')->insert([
            'id' => $entityV2Id,
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 2,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $reviewerId,
            'published_at' => $now,
            'changelog' => 'Solo en entity_versions v2',
            'snapshot_data' => json_encode(['blocks' => []], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->getJson("/api/v1/documents/{$docId}/template-version-status", $hCreator)
            ->assertOk()
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.changelog', 'Solo en entity_versions v2')
            ->assertJsonPath('data.current_version.version_number', 1)
            ->assertJsonPath('data.latest_version.version_number', 2)
            ->assertJsonPath('data.latest_version.id', $entityV2Id);
    }

    public function test_template_version_status_uses_legacy_when_entity_has_lower_version_number(): void
    {
        $creatorId = (string) Str::uuid();
        $this->grantPermissionsForUser($creatorId);
        $reviewerId = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer($creatorId, $reviewerId);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla legacy gana',
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

        TemplateBlock::query()->forceCreate([
            'id' => $b1,
            'template_id' => $tid,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
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
            'title' => 'Doc legacy latest',
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $hCreator)->assertCreated();

        $docId = (string) $createDoc->json('data.id');

        $legacyV2Id = (string) Str::uuid();
        $now = now();
        DB::table('entity_versions')->insert([
            'id' => $legacyV2Id,
            'versionable_type' => Template::class,
            'versionable_id' => $tid,
            'version_number' => 2,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $reviewerId,
            'published_by' => $reviewerId,
            'published_at' => $now,
            'changelog' => 'legacy v2',
            'snapshot_data' => json_encode(['blocks' => []], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->getJson("/api/v1/documents/{$docId}/template-version-status", $hCreator)
            ->assertOk()
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.changelog', 'legacy v2')
            ->assertJsonPath('data.latest_version.version_number', 2)
            ->assertJsonPath('data.latest_version.id', $legacyV2Id);
    }
}
