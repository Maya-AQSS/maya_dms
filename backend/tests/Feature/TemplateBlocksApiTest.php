<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
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
            'type' => 'paragraph',
            'title' => 'Bloque',
            'default_content' => null,
            'block_state' => 'editable',
            'mandatory' => false,
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

    public function test_state_change_creates_audit_log_with_old_and_new_values(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);
        [, $blockId] = $this->seedTemplateAndBlock($userId);

        $this->putJson("/api/v1/blocks/{$blockId}", [
            'block_state' => 'locked',
        ], $headers)->assertOk();

        $row = DB::table('audit_log')
            ->where('action', 'block_state_changed')
            ->where('block_id', $blockId)
            ->orderByDesc('timestamp')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($userId, $row->user_id);
        $this->assertSame($blockId, $row->block_id);

        $previous = json_decode((string) $row->previous_value, true);
        $new = json_decode((string) $row->new_value, true);

        $this->assertSame('editable', $previous['block_state'] ?? null);
        $this->assertSame(false, $previous['mandatory'] ?? null);
        $this->assertSame('locked', $new['block_state'] ?? null);
        $this->assertSame(false, $new['mandatory'] ?? null);
        $this->assertNotNull($row->timestamp);
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
}

