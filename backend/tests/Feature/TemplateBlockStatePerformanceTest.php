<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class TemplateBlockStatePerformanceTest extends TestCase
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
     * Requisito técnico: el cambio de estado de un bloque debe persistir en < 200ms.
     */
    public function test_block_state_update_performance(): void
    {
        $userId = (string) Str::uuid();
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Perf Template',
            'organization_id' => 'org-1',
            'created_by' => $userId,
            'status' => 'draft',
            'visibility_level' => 'personal',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $blockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $blockId,
            'template_id' => $templateId,
            'type' => 'paragraph',
            'title' => 'B1',
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ]);

        $headers = $this->authHeaders($userId);

        $start = microtime(true);

        $response = $this->putJson("/api/v1/blocks/{$blockId}", [
            'block_state' => 'locked',
        ], $headers);

        $end = microtime(true);
        $durationMs = ($end - $start) * 1000;

        $response->assertOk();
        $this->assertEquals('locked', TemplateBlock::find($blockId)->block_state);
        
        // Assert performance < 200ms
        $this->assertLessThan(200, $durationMs, "El cambio de estado tardó {$durationMs}ms, excediendo el límite de 200ms.");
    }
}
