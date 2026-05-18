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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Tests for TemplateBlockBulkController: reorder and bulkUpdate.
 *
 * The existing TemplateBlocksApiTest already covers:
 *   - reorder validation (block_ids must include all template blocks)
 *   - reorder happy path (sort_order updated atomically)
 *   - bulkUpdate 403 when non-owner has only templates.read on a global template
 *
 * This file targets the uncovered lines:
 *   - Line 74: abort(403) when template not found in findManyByIds (user_access scope hides it)
 *   - Lines 81-86: process_id query param validation (mismatch → 403, match → success)
 *   - Lines 88-99: DTO construction and bulkUpdate call with owned template
 */
class TemplateBlockBulkApiTest extends TestCase
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
    private function authHeaders(string $sub, array $codes = ['templates.read', 'templates.update']): array
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
     * Seeds a personal template with one editable block owned by $ownerId.
     *
     * @return array{templateId: string, blockId: string, processId: string}
     */
    private function seedTemplateWithBlock(string $ownerId, ?string $processId = null): array
    {
        // Use the default process if none provided
        $processId ??= '00000000-0000-0000-0000-000000000001';

        $templateId = (string) Str::uuid();
        $blockId    = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'process_id'       => $processId,
            'name'             => 'Plantilla Bulk Test',
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
            'title'           => 'Bloque Bulk',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 0,
        ]);

        return [
            'templateId' => $templateId,
            'blockId'    => $blockId,
            'processId'  => $processId,
        ];
    }

    // ─── bulkUpdate — authentication ──────────────────────────────────────────

    public function test_bulk_update_requires_authentication(): void
    {
        $this->putJson('/api/v1/blocks/bulk', [
            'ids'         => [(string) Str::uuid()],
            'block_state' => BlockState::Locked->value,
        ])->assertUnauthorized();
    }

    // ─── bulkUpdate — 403 when template hidden by user_access scope ───────────

    /**
     * If the blocks belong to another user's personal template, findManyByIds
     * returns null for that template ID (user_access global scope filters it out),
     * triggering abort(403) at line 74.
     */
    public function test_bulk_update_returns_403_when_template_hidden_from_requesting_user(): void
    {
        $ownerId  = (string) Str::uuid();
        $stranger = (string) Str::uuid();

        $ctx     = $this->seedTemplateWithBlock($ownerId);
        $headers = $this->authHeaders($stranger);

        $this->putJson('/api/v1/blocks/bulk', [
            'ids'         => [$ctx['blockId']],
            'block_state' => BlockState::Locked->value,
        ], $headers)->assertForbidden();
    }

    // ─── bulkUpdate — happy path for template owner ───────────────────────────

    public function test_bulk_update_changes_block_state_for_template_owner(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $response = $this->putJson('/api/v1/blocks/bulk', [
            'ids'         => [$ctx['blockId']],
            'block_state' => BlockState::Locked->value,
        ], $headers)->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame($ctx['blockId'], $data[0]['id']);
        $this->assertSame(BlockState::Locked->value, $data[0]['block_state']);
    }

    // ─── bulkUpdate — process_id query param ─────────────────────────────────

    /**
     * If ?process_id matches the template's process_id, the request succeeds.
     * This covers lines 81-86 of TemplateBlockBulkController (the matching branch).
     */
    public function test_bulk_update_succeeds_when_process_id_query_matches_template(): void
    {
        $userId    = (string) Str::uuid();
        $processId = '00000000-0000-0000-0000-000000000001'; // default process from TestCase
        $ctx       = $this->seedTemplateWithBlock($userId, $processId);
        $headers   = $this->authHeaders($userId);

        $this->putJson(
            '/api/v1/blocks/bulk?process_id='.$processId,
            [
                'ids'         => [$ctx['blockId']],
                'block_state' => BlockState::Locked->value,
            ],
            $headers,
        )->assertOk();
    }

    /**
     * If ?process_id does NOT match the template's process_id, the request is
     * aborted with 403 via assertOptionalProcessContextMatches().
     * This covers lines 82-86 (the mismatch branch).
     */
    public function test_bulk_update_returns_403_when_process_id_query_does_not_match(): void
    {
        $userId    = (string) Str::uuid();
        $processId = '00000000-0000-0000-0000-000000000001';
        $ctx       = $this->seedTemplateWithBlock($userId, $processId);
        $headers   = $this->authHeaders($userId);

        $wrongProcessId = (string) Str::uuid();

        $this->putJson(
            '/api/v1/blocks/bulk?process_id='.$wrongProcessId,
            [
                'ids'         => [$ctx['blockId']],
                'block_state' => BlockState::Locked->value,
            ],
            $headers,
        )->assertForbidden();
    }

    /**
     * An empty string ?process_id= is treated as absent — no mismatch check.
     * Ensures the guard `$givenProcessId !== ''` works correctly.
     */
    public function test_bulk_update_ignores_empty_process_id_query_param(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $this->putJson(
            '/api/v1/blocks/bulk?process_id=',
            [
                'ids'         => [$ctx['blockId']],
                'block_state' => BlockState::Locked->value,
            ],
            $headers,
        )->assertOk();
    }

    // ─── bulkUpdate — validation ──────────────────────────────────────────────

    public function test_bulk_update_rejects_invalid_block_state(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $this->putJson('/api/v1/blocks/bulk', [
            'ids'         => [$ctx['blockId']],
            'block_state' => 'invalid_state',
        ], $headers)->assertUnprocessable()
            ->assertJsonValidationErrors(['block_state']);
    }

    public function test_bulk_update_rejects_when_ids_array_is_empty(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->putJson('/api/v1/blocks/bulk', [
            'ids'         => [],
            'block_state' => BlockState::Locked->value,
        ], $headers)->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_update_rejects_when_block_id_does_not_exist(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        // Block ID that does not exist in DB — findBlocksByIdsOrFail throws ValidationException
        $this->putJson('/api/v1/blocks/bulk', [
            'ids'         => [(string) Str::uuid()],
            'block_state' => BlockState::Locked->value,
        ], $headers)->assertUnprocessable();
    }

    // ─── reorder — authentication ─────────────────────────────────────────────

    public function test_reorder_requires_authentication(): void
    {
        $this->patchJson('/api/v1/templates/'.Str::uuid().'/blocks/reorder', [
            'block_ids' => [(string) Str::uuid()],
        ])->assertUnauthorized();
    }

    // ─── reorder — 403/404 for non-owner ─────────────────────────────────────

    public function test_reorder_returns_403_for_non_creator_on_personal_template(): void
    {
        $ownerId  = (string) Str::uuid();
        $stranger = (string) Str::uuid();
        $ctx      = $this->seedTemplateWithBlock($ownerId);
        $headers  = $this->authHeaders($stranger);

        $this->patchJson("/api/v1/templates/{$ctx['templateId']}/blocks/reorder", [
            'block_ids' => [$ctx['blockId']],
        ], $headers)->assertForbidden();
    }

    // ─── reorder — happy path ─────────────────────────────────────────────────

    public function test_reorder_updates_sort_order_for_two_blocks(): void
    {
        $userId    = (string) Str::uuid();
        $ctx       = $this->seedTemplateWithBlock($userId);
        $secondId  = (string) Str::uuid();

        TemplateBlock::query()->forceCreate([
            'id'              => $secondId,
            'template_id'     => $ctx['templateId'],
            'title'           => 'Segundo Bloque',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 1,
        ]);

        $headers = $this->authHeaders($userId);

        // Reverse the order: second block first, first block second
        $this->patchJson("/api/v1/templates/{$ctx['templateId']}/blocks/reorder", [
            'block_ids' => [$secondId, $ctx['blockId']],
        ], $headers)->assertNoContent();

        $orders = TemplateBlock::query()
            ->where('template_id', $ctx['templateId'])
            ->pluck('sort_order', 'id');

        $this->assertSame(1, $orders[$secondId]);
        $this->assertSame(2, $orders[$ctx['blockId']]);
    }
}
