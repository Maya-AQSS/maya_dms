<?php

declare(strict_types=1);

namespace Tests\Feature\TemplateBlocks;

use App\Enums\BlockKind;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class TocUniquePerTemplateTest extends TestCase
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
     * @return array<string, string>
     */
    private function authHeaders(string $sub): array
    {
        auth()->forgetUser();

        $this->assignUserPermissions($sub, ['template.show', 'template.create_block']);

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

    private function makePersonalDraftTemplate(string $creatorId): string
    {
        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla TOC test',
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

        return $tid;
    }

    public function test_create_first_toc_block_succeeds_with_201(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId);
        $tid = $this->makePersonalDraftTemplate($creatorId);

        $response = $this->postJson("/api/v1/templates/{$tid}/blocks", [
            'title' => 'Índice',
            'kind' => BlockKind::Toc->value,
            'block_state' => 'locked',
            'sort_order' => 0,
        ], $headers);

        $response->assertCreated();
        $this->assertDatabaseHas('template_blocks', [
            'template_id' => $tid,
            'kind' => BlockKind::Toc->value,
        ]);
    }

    public function test_create_second_toc_block_fails_with_422(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId);
        $tid = $this->makePersonalDraftTemplate($creatorId);

        // Create first TOC block
        $this->postJson("/api/v1/templates/{$tid}/blocks", [
            'title' => 'Índice 1',
            'kind' => BlockKind::Toc->value,
            'block_state' => 'locked',
            'sort_order' => 0,
        ], $headers)->assertCreated();

        // Attempt to create second TOC block
        $response = $this->postJson("/api/v1/templates/{$tid}/blocks", [
            'title' => 'Índice 2',
            'kind' => BlockKind::Toc->value,
            'block_state' => 'locked',
            'sort_order' => 1,
        ], $headers);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['kind']);
        $this->assertStringContainsString(
            'Solo se permite un bloque de índice',
            $response->json('errors.kind.0')
        );
    }

    public function test_create_block_with_invalid_kind_fails_with_422(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId);
        $tid = $this->makePersonalDraftTemplate($creatorId);

        $response = $this->postJson("/api/v1/templates/{$tid}/blocks", [
            'title' => 'Bloque inválido',
            'kind' => 'invalid_kind',
            'block_state' => 'editable',
            'sort_order' => 0,
        ], $headers);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['kind']);
    }

    public function test_create_block_without_kind_defaults_to_content(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId);
        $tid = $this->makePersonalDraftTemplate($creatorId);

        $response = $this->postJson("/api/v1/templates/{$tid}/blocks", [
            'title' => 'Bloque sin kind',
            'default_content' => ['text' => 'contenido'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ], $headers);

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertEquals(BlockKind::Content->value, $data['kind']);

        $this->assertDatabaseHas('template_blocks', [
            'template_id' => $tid,
            'kind' => BlockKind::Content->value,
        ]);
    }
}
