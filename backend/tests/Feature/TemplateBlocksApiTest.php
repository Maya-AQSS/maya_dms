<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateBlock;
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
    private function seedTemplateAndBlock(string $userId): array
    {
        $templateId = (string) Str::uuid();
        $blockId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla bloques',
            'description' => null,
            'visibility_level' => 'personal',
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
}

