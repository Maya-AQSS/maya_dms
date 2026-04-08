<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\JwksServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JwksServiceTest extends TestCase
{
    // ── Escenario 3: claves cacheadas con TTL de 1 hora ───────────────────────

    public function test_fetched_keys_are_cached_with_1_hour_ttl(): void
    {
        $cache = Cache::spy();
        $cache->shouldReceive('get')->andReturn(null);

        Http::fake(['*' => Http::response($this->minimalJwks('k1'), 200)]);
        config(['auth.jwks_url' => 'https://example.com/.well-known/jwks.json']);

        try {
            app(JwksServiceInterface::class)->getPublicKey('k1');
        } catch (\Throwable) {
            // La clave puede ser inválida; lo que validamos es el PUT al caché
        }

        $cache->shouldHaveReceived('put')
            ->withArgs(fn ($key, $value, $ttl) => $key === 'jwks_keys' && $ttl === 3600)
            ->atLeast()->once();
    }

    // ── Escenario 3: fallback a caché cuando el endpoint no responde ──────────

    public function test_uses_cached_keys_when_endpoint_is_unavailable(): void
    {
        $publicKey = $this->generatePublicKey();
        Cache::put('jwks_keys', ['test-kid' => $publicKey], 3600);

        Http::fake(['*' => Http::response(null, 503)]);
        config(['auth.jwks_url' => 'https://example.com/.well-known/jwks.json']);

        $result = app(JwksServiceInterface::class)->getPublicKey('test-kid');

        $this->assertNotNull($result);
    }

    // ── Escenario 3: excepción cuando no hay caché y el endpoint cae ──────────

    public function test_throws_when_no_cache_and_endpoint_is_unavailable(): void
    {
        Cache::forget('jwks_keys');

        Http::fake(['*' => Http::response(null, 503)]);
        config(['auth.jwks_url' => 'https://example.com/.well-known/jwks.json']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/JWKS unavailable/');

        app(JwksServiceInterface::class)->getPublicKey('any-kid');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function minimalJwks(string $kid): array
    {
        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'kid' => $kid,
                    'n'   => 'somerandombase64urlvalue',
                    'e'   => 'AQAB',
                ],
            ],
        ];
    }

    private function generatePublicKey(): string
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $details = openssl_pkey_get_details($resource);

        return $details['key'];
    }
}
