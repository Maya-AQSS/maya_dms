<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Tests for TemplateBlockController: index, show, store, destroy.
 *
 * The update (PUT /blocks/{block}) path is covered by TemplateBlocksApiTest.
 * This file targets the uncovered 39% of TemplateBlockController lines.
 */
class TemplateBlockCrudApiTest extends TestCase
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

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes = [
        'template.show',
        'block.index',
        'block.show',
        'block.create',
        'block.update',
        'block.delete',
        'template.update',
    ]): array
    {
        auth()->forgetUser();
        $this->assignUserPermissions($sub, $codes);

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

    /**
     * @return array{templateId: string, blockId: string, ownerId: string}
     */
    private function seedTemplateWithBlock(
        string $ownerId,
        string $blockState = 'editable',
    ): array {
        $templateId = (string) Str::uuid();
        $blockId    = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Plantilla CRUD',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $blockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque CRUD',
            'default_content' => null,
            'block_state'     => $blockState,
            'sort_order'      => 0,
        ]);

        return [
            'templateId' => $templateId,
            'blockId'    => $blockId,
            'ownerId'    => $ownerId,
        ];
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/templates/'.Str::uuid().'/blocks')
            ->assertUnauthorized();
    }

    public function test_index_returns_404_for_unknown_template(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->getJson('/api/v1/templates/'.Str::uuid().'/blocks', $headers)
            ->assertNotFound();
    }

    public function test_index_returns_blocks_for_template_creator(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $response = $this->getJson("/api/v1/templates/{$ctx['templateId']}/blocks", $headers)
            ->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame($ctx['blockId'], $data[0]['id']);
        $this->assertArrayHasKey('title', $data[0]);
        $this->assertArrayHasKey('block_state', $data[0]);
        $this->assertArrayHasKey('sort_order', $data[0]);
    }

    public function test_index_returns_403_for_non_creator_on_personal_template(): void
    {
        $ownerId  = (string) Str::uuid();
        $stranger = (string) Str::uuid();
        $ctx      = $this->seedTemplateWithBlock($ownerId);
        $headers  = $this->authHeaders($stranger);

        $this->getJson("/api/v1/templates/{$ctx['templateId']}/blocks", $headers)
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/v1/blocks/'.Str::uuid())
            ->assertUnauthorized();
    }

    public function test_show_returns_404_for_unknown_block(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->getJson('/api/v1/blocks/'.Str::uuid(), $headers)
            ->assertNotFound();
    }

    public function test_show_returns_block_for_creator(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $response = $this->getJson("/api/v1/blocks/{$ctx['blockId']}", $headers)
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame($ctx['blockId'], $data['id']);
        $this->assertSame('Bloque CRUD', $data['title']);
        $this->assertSame('editable', $data['block_state']);
    }

    public function test_show_returns_403_for_non_creator_on_personal_template(): void
    {
        $ownerId  = (string) Str::uuid();
        $stranger = (string) Str::uuid();
        $ctx      = $this->seedTemplateWithBlock($ownerId);
        $headers  = $this->authHeaders($stranger);

        $this->getJson("/api/v1/blocks/{$ctx['blockId']}", $headers)
            ->assertForbidden();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/templates/'.Str::uuid().'/blocks', [])
            ->assertUnauthorized();
    }

    public function test_store_creates_block_for_creator(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId, ['template.show', 'template.update', 'block.create']);

        $response = $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'       => 'Nuevo Bloque',
            'block_state' => BlockState::Editable->value,
        ], $headers)->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Nuevo Bloque', $data['title']);
        $this->assertSame(BlockState::Editable->value, $data['block_state']);
        $this->assertSame($ctx['templateId'], $data['template_id']);
    }

    public function test_store_rejects_invalid_block_state(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId, ['template.show', 'template.update']);

        $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'       => 'Bloque inválido',
            'block_state' => 'invalid_state',
        ], $headers)->assertUnprocessable()
            ->assertJsonValidationErrors(['block_state']);
    }

    public function test_store_returns_403_for_non_creator(): void
    {
        $ownerId  = (string) Str::uuid();
        $stranger = (string) Str::uuid();
        $ctx      = $this->seedTemplateWithBlock($ownerId);
        $headers  = $this->authHeaders($stranger, ['template.show', 'template.update']);

        $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'       => 'Intento de bloque',
            'block_state' => BlockState::Editable->value,
        ], $headers)->assertForbidden();
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/blocks/'.Str::uuid())
            ->assertUnauthorized();
    }

    public function test_destroy_returns_404_for_unknown_block(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->deleteJson('/api/v1/blocks/'.Str::uuid(), [], $headers)
            ->assertNotFound();
    }

    public function test_destroy_removes_block_for_creator(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId, BlockState::Optional->value);

        // Add a second block so template still has the editable constraint satisfied
        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $ctx['templateId'],
            'title'           => 'Bloque editable retenido',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 1,
        ]);

        $headers = $this->authHeaders($userId, ['template.show', 'template.update']);

        $this->deleteJson("/api/v1/blocks/{$ctx['blockId']}", [], $headers)
            ->assertNoContent();

        $this->assertSoftDeleted('template_blocks', ['id' => $ctx['blockId']]);
    }

    public function test_destroy_returns_403_for_non_creator(): void
    {
        $ownerId  = (string) Str::uuid();
        $stranger = (string) Str::uuid();
        $ctx      = $this->seedTemplateWithBlock($ownerId, BlockState::Optional->value);

        // Add a second block to avoid invariant check issues
        TemplateBlock::query()->forceCreate([
            'id'              => (string) Str::uuid(),
            'template_id'     => $ctx['templateId'],
            'title'           => 'Bloque retenido',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 1,
        ]);

        $headers = $this->authHeaders($stranger, ['template.show', 'template.update']);

        $this->deleteJson("/api/v1/blocks/{$ctx['blockId']}", [], $headers)
            ->assertForbidden();
    }
}
