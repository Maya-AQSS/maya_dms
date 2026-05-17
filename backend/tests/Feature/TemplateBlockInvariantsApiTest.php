<?php

namespace Tests\Feature;

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

/**
 * Invariantes de bloque que se aplican en submit-review y publish:
 * - La plantilla debe tener al menos un bloque editable o modificable.
 * - Los bloques editables y modificables no pueden tener contenido vacío.
 * - Los bloques bloqueados no pueden tener contenido vacío.
 */
class TemplateBlockInvariantsApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
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

        $this->assignUserPermissions($sub, ['templates.read']);

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
            'id'               => $tid,
            'name'             => 'Plantilla invariantes',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id'    => null,
            'study_id'         => null,
            'module_id'        => null,
            'team_id'          => null,
            'created_by'       => $creatorId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'parallel',
        ]);

        return $tid;
    }

    // ── submit-review path ──────────────────────────────────────────────────

    public function test_submit_review_fails_when_all_blocks_are_locked_with_no_editable(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Bloqueado',
            'default_content' => ['text' => 'Contenido fijo'],
            'block_state'     => 'locked',
            'sort_order'      => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'La plantilla debe tener al menos un bloque editable o modificable.');
    }

    public function test_submit_review_fails_when_editable_block_has_null_default_content(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Editable vacío',
            'default_content' => null,
            'block_state'     => 'editable',
            'sort_order'      => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'Los bloques editables y modificables no pueden estar vacíos.');
    }

    public function test_submit_review_fails_when_editable_block_has_empty_array_default_content(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Editable array vacío',
            'default_content' => [],
            'block_state'     => 'editable',
            'sort_order'      => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'Los bloques editables y modificables no pueden estar vacíos.');
    }

    public function test_submit_review_fails_when_locked_block_has_null_default_content(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        // Editable con contenido para superar la primera validación
        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Editable con contenido',
            'default_content' => ['text' => 'ok'],
            'block_state'     => 'editable',
            'sort_order'      => 0,
        ]);
        // Locked sin contenido: debe fallar
        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Bloqueado vacío',
            'default_content' => null,
            'block_state'     => 'locked',
            'sort_order'      => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'Los bloques bloqueados no pueden estar vacíos.');
    }

    public function test_submit_review_personal_template_auto_publishes_when_blocks_filled(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Editable con contenido',
            'default_content' => ['text' => 'Plantilla completa'],
            'block_state'     => 'editable',
            'sort_order'      => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/submit-review", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    // ── publish path ────────────────────────────────────────────────────────

    public function test_publish_fails_when_all_blocks_are_locked_with_no_editable(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Bloqueado',
            'default_content' => ['text' => 'Contenido fijo'],
            'block_state'     => 'locked',
            'sort_order'      => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'La plantilla debe tener al menos un bloque editable o modificable.');
    }

    public function test_publish_fails_when_editable_block_has_empty_content(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Editable vacío',
            'default_content' => null,
            'block_state'     => 'editable',
            'sort_order'      => 0,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'Los bloques editables y modificables no pueden estar vacíos.');
    }

    public function test_publish_fails_when_locked_block_has_empty_content(): void
    {
        $creatorId = (string) Str::uuid();
        $headers   = $this->authHeaders($creatorId);
        $tid       = $this->makePersonalDraftTemplate($creatorId);

        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Editable con contenido',
            'default_content' => ['text' => 'ok'],
            'block_state'     => 'editable',
            'sort_order'      => 0,
        ]);
        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $tid,
            'title'           => 'Bloqueado vacío',
            'default_content' => null,
            'block_state'     => 'locked',
            'sort_order'      => 1,
        ]);

        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', 'Los bloques bloqueados no pueden estar vacíos.');
    }
}
