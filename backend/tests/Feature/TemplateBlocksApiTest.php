<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
use Maya\Messaging\Publishers\AuditPublisher;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class TemplateBlocksApiTest extends TestCase
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

    private function grantTemplatesReadOnly(string $userId): void
    {
        DB::table('user_permissions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'permission_code' => 'templates.read',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $sub): array
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
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedTemplateAndBlock(string $userId, bool $globalVisibility = false): array
    {
        $templateId = (string) Str::uuid();
        $blockId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla bloques',
            'description' => null,
            'visibility_level' => $globalVisibility
                ? TemplateVisibilityLevel::Global->value
                : 'personal',
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $blockId,
            'template_id' => $templateId,
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        return [$templateId, $blockId];
    }

    public function test_update_block_rejects_invalid_block_state_with_422(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);
        [, $blockId] = $this->seedTemplateAndBlock($userId);

        $response = $this->putJson("/api/v1/blocks/{$blockId}", [
            'block_state' => 'invalid_state',
        ], $headers);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['block_state']);
    }

    public function test_state_change_publishes_audit_event_with_old_and_new_values(): void
    {
        $userId = (string) Str::uuid();
        [, $blockId] = $this->seedTemplateAndBlock($userId);

        $capturedArgs = null;
        $auditPublisher = $this->mock(AuditPublisher::class);
        $auditPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function () use (&$capturedArgs): bool {
                $capturedArgs = func_get_args();
                return true;
            });

        $headers = $this->authHeaders($userId);
        $this->putJson("/api/v1/blocks/{$blockId}", [
            'block_state' => 'locked',
        ], $headers)->assertOk();

        $this->assertNotNull($capturedArgs);
        // positional: applicationSlug, entityType, entityId, action, userId, blockId, previousValue, newValue
        $this->assertSame('maya-dms', $capturedArgs[0]);
        $this->assertSame('template', $capturedArgs[1]);
        $this->assertSame('block_state_changed', $capturedArgs[3]);
        $this->assertSame($userId, $capturedArgs[4]);
        $this->assertSame($blockId, $capturedArgs[5]);
        $this->assertSame('editable', ($capturedArgs[6] ?? [])['block_state'] ?? null);
        $this->assertSame('locked', ($capturedArgs[7] ?? [])['block_state'] ?? null);
    }

    public function test_user_with_only_templates_read_cannot_mutate_blocks_on_foreign_template(): void
    {
        $ownerId = (string) Str::uuid();
        $readerId = (string) Str::uuid();
        $this->grantTemplatesReadOnly($readerId);

        $readerHeaders = $this->authHeaders($readerId);
        [, $blockId] = $this->seedTemplateAndBlock($ownerId, true);
        $templateId = TemplateBlock::query()->findOrFail($blockId)->template_id;

        $this->putJson("/api/v1/blocks/{$blockId}", [
            'block_state' => 'locked',
        ], $readerHeaders)->assertForbidden();

        $this->putJson('/api/v1/blocks/bulk', [
            'ids' => [$blockId],
            'block_state' => 'locked',
        ], $readerHeaders)->assertForbidden();

        $this->postJson("/api/v1/templates/{$templateId}/blocks", [
            'type' => 'paragraph',
        ], $readerHeaders)->assertForbidden();
    }

    public function test_reorder_requires_all_template_blocks(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);
        [$templateId, $firstBlockId] = $this->seedTemplateAndBlock($userId);
        $secondBlockId = (string) Str::uuid();

        TemplateBlock::query()->forceCreate([
            'id' => $secondBlockId,
            'template_id' => $templateId,
            'title' => 'Bloque 2',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 1,
        ]);

        $this->patchJson("/api/v1/templates/{$templateId}/blocks/reorder", [
            'block_ids' => [$firstBlockId],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['block_ids']);
    }

    public function test_reorder_updates_sort_order_atomically_for_full_set(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);
        [$templateId, $firstBlockId] = $this->seedTemplateAndBlock($userId);
        $secondBlockId = (string) Str::uuid();

        TemplateBlock::query()->forceCreate([
            'id' => $secondBlockId,
            'template_id' => $templateId,
            'title' => 'Bloque 2',
            'default_content' => null,
            'block_state' => 'editable',
            'sort_order' => 1,
        ]);

        $this->patchJson("/api/v1/templates/{$templateId}/blocks/reorder", [
            'block_ids' => [$secondBlockId, $firstBlockId],
        ], $headers)->assertNoContent();

        $orders = TemplateBlock::query()
            ->where('template_id', $templateId)
            ->pluck('sort_order', 'id');

        $this->assertSame(1, $orders[$secondBlockId]);
        $this->assertSame(2, $orders[$firstBlockId]);
    }
}

