<?php

namespace Tests\Feature;

use Maya\Auth\Middleware\JwtMiddleware;
use App\Services\Contracts\HealthCheckServiceInterface;
use Maya\Auth\Contracts\JwksServiceInterface;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Mockery;
use Tests\TestCase;

class JwtMiddlewareTest extends TestCase
{
    // ── Escenario 1 + 4: ruta protegida sin token ─────────────────────────────

    public function test_protected_route_returns_401_without_token(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthenticated']);
    }

    // ── Escenario 4: token con formato incorrecto ─────────────────────────────

    public function test_malformed_token_returns_401(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not.valid.token'])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthenticated']);
    }

    // ── Escenario 4: cuerpo JSON usa 'error', no 'message' ───────────────────

    public function test_401_response_uses_error_key_not_message(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonStructure(['error'])
            ->assertJsonMissing(['message']);
    }

    // ── Escenario 1: /health excluido del middleware JWT ─────────────────────

    public function test_health_endpoints_are_accessible_without_jwt(): void
    {
        $mock = $this->mock(HealthCheckServiceInterface::class);
        $mock->shouldReceive('checkAll')
            ->once()
            ->andReturn(['status' => 'ok', 'services' => []]);
        $mock->shouldReceive('checkReadiness')
            ->once()
            ->andReturn(['status' => 'ok', 'services' => []]);

        $this->getJson('/api/v1/health')->assertStatus(200);
        $this->getJson('/api/v1/health/live')->assertStatus(200);
        $this->getJson('/api/v1/health/ready')->assertStatus(200);
    }

    // ── Escenario 5: log incluye IP y User-Agent ──────────────────────────────

    public function test_auth_failure_logs_ip_and_user_agent(): void
    {
        $spy = Log::spy();

        $this->withHeaders(['User-Agent' => 'TestClient/2.0'])
            ->getJson('/api/v1/me');

        $spy->shouldHaveReceived('warning')
            ->with(
                'JWT authentication failed',
                Mockery::on(fn (array $context) =>
                    isset($context['ip'])
                    && $context['user_agent'] === 'TestClient/2.0'
                )
            );
    }

    // ── Escenario 5: log incluye hint (8 chars), nunca el token completo ──────

    public function test_auth_failure_logs_token_hint_not_full_token(): void
    {
        $spy = Log::spy();

        $token = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6InRlc3QifQ.fakepayload.fakesig';

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me');

        $spy->shouldHaveReceived('warning')
            ->with(
                'JWT authentication failed',
                Mockery::on(fn (array $context) =>
                    ($context['token_hint'] ?? null) === substr($token, 0, 8)
                    && ! array_key_exists('token', $context)
                )
            );
    }

    // ── Escenario 2: firma RS256, iss, aud y exp validados correctamente ────────

    public function test_valid_rs256_token_signature_and_claims_are_accepted(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();
        $kid   = 'test-kid-e2';
        $token = $this->buildJwt($privatePem, $publicPem, $kid);

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $middleware = app(JwtMiddleware::class);

        // Testear directamente la validación criptográfica (E2) sin
        // depender de auth()->setUser() ni de sesión activa en el entorno de pruebas
        $method = (new \ReflectionClass(JwtMiddleware::class))
            ->getMethod('validateAndExtractClaims');
        $method->setAccessible(true);

        $claims = $method->invoke($middleware, $token);

        $this->assertSame('user-test-uuid', $claims['sub']);
        $this->assertSame('test@example.com', $claims['email']);
    }

    // ── Escenario 4 ampliado: casos específicos de token inválido ────────────

    public function test_expired_token_returns_401(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $token = $this->buildJwt($privatePem, $publicPem, 'kid-exp', expiredAt: true);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthenticated']);
    }

    public function test_token_with_wrong_issuer_returns_401(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        config([
            'auth.jwt_issuer'   => 'expected-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $token = $this->buildJwt($privatePem, $publicPem, 'kid-iss', issuer: 'wrong-issuer');

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthenticated']);
    }

    public function test_token_with_wrong_audience_returns_401(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'expected-audience',
        ]);

        $token = $this->buildJwt($privatePem, $publicPem, 'kid-aud', audience: 'wrong-audience');

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthenticated']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateRsaKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);

        return [$privatePem, $details['key']];
    }

    private function buildJwt(
        string $privatePem,
        string $publicPem,
        string $kid,
        bool $expiredAt = false,
        string $issuer = 'test-issuer',
        string $audience = 'test-audience',
    ): string {
        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($privatePem),
            InMemory::plainText($publicPem),
        );

        $now = new DateTimeImmutable();
        $exp = $expiredAt
            ? $now->modify('-1 hour')
            : $now->modify('+1 hour');

        return $config->builder()
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->issuedAt($now->modify('-2 hours'))
            ->canOnlyBeUsedAfter($now->modify('-2 hours'))
            ->expiresAt($exp)
            ->relatedTo('user-test-uuid')
            ->withClaim('email', 'test@example.com')
            ->withHeader('kid', $kid)
            ->getToken(new Sha256(), InMemory::plainText($privatePem))
            ->toString();
    }
}
