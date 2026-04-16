<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Group;
use App\Models\Template;
use App\Models\TemplateBlock;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * F-03.4 Escenario 4: documento anclado a template_version_id; al abrir, estructura de esa versión.
 */
class DocumentsTemplateVersionApiTest extends TestCase
{
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
            'auth.template_shared_visibility_roles' => ['department-head', 'director'],
        ]);

        Cache::flush();
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

    public function test_store_document_requires_published_template_version(): void
    {
        $userId = (string) Str::uuid();
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
            'group_id' => null,
            'organization_id' => 'org-x',
            'created_by' => $userId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Mi doc',
            'organization_id' => 'org-x',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_show_document_uses_v1_snapshot_after_template_publishes_v2(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
            ['department-head'],
            ['organization_id' => 'org-x'],
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
            'group_id' => null,
            'organization_id' => 'org-x',
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
            'title' => 'Solo v1',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Expediente',
            'organization_id' => 'org-x',
        ], $hCreator);

        $createDoc->assertCreated();
        $docId = $createDoc->json('data.id');
        $versionIdV1 = $createDoc->json('data.template_version_id');
        $this->assertNotEmpty($versionIdV1);
        $createDoc->assertJsonCount(1, 'data.blocks');
        $createDoc->assertJsonPath('data.blocks.0.type', 'heading');
        $createDoc->assertJsonPath('data.blocks.0.title', 'Solo v1');

        $b2 = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $b2,
            'template_id' => $tid,
            'type' => 'paragraph',
            'title' => 'Añadido en v2',
            'default_content' => ['x' => 1],
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/reopen-draft", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v2 segundo bloque'], $hReviewer)->assertOk();

        $show = $this->getJson("/api/v1/documents/{$docId}", $hCreator);
        $show->assertOk();
        $show->assertJsonPath('data.template_version_id', $versionIdV1);
        $show->assertJsonCount(1, 'data.blocks');
        $show->assertJsonPath('data.blocks.0.type', 'heading');
        $show->assertJsonPath('data.blocks.0.title', 'Solo v1');
        $show->assertJsonPath('data.team', null);
    }

    public function test_show_document_includes_team_when_template_is_group_scoped(): void
    {
        $creatorId = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();
        [$hCreator, $hReviewer] = $this->authHeadersCreatorAndReviewer(
            $creatorId,
            $reviewerId,
            ['department-head'],
            ['organization_id' => 'org-x'],
        );

        $gid = (string) Str::uuid();
        Group::query()->forceCreate([
            'id' => $gid,
            'name' => 'Equipo doc',
            'description' => null,
            'owner_id' => $creatorId,
            'is_department' => true,
        ]);

        $tid = (string) Str::uuid();
        $b1 = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla de equipo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Group->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'group_id' => $gid,
            'organization_id' => 'org-x',
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

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $hCreator)->assertOk();
        $this->postJson("/api/v1/templates/{$tid}/publish", ['changelog' => 'v1'], $hReviewer)->assertOk();

        $createDoc = $this->postJson('/api/v1/documents', [
            'template_id' => $tid,
            'title' => 'Expediente equipo',
            'organization_id' => 'org-x',
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
}
