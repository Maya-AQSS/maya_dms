<?php

namespace Tests\Feature;

use App\Http\Middleware\JwtMiddleware;
use App\Models\JwtUser;
use App\Services\JwksService;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Tests\TestCase;

class JwtValidationTest extends TestCase
{
    // ── Escenario 1: sub identifica unívocamente al usuario ───────────────────

    public function test_sub_claim_is_extracted_as_user_id(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksService::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $middleware = app(JwtMiddleware::class);
        $method     = (new \ReflectionClass(JwtMiddleware::class))
            ->getMethod('validateAndExtractClaims');
        $method->setAccessible(true);

        $token  = $this->buildJwt($privatePem, $publicPem, userId: 'user-uuid-123');
        $claims = $method->invoke($middleware, $token);

        $this->assertSame('user-uuid-123', $claims['sub']);
    }

    public function test_jwt_user_exposes_id_from_sub_claim(): void
    {
        $user = new JwtUser([
            'id'         => 'user-uuid-123',
            'email'      => 'test@example.com',
            'name'       => 'Test User',
            'department' => 'Engineering',
        ]);

        $this->assertSame('user-uuid-123', $user->id);
        $this->assertSame('user-uuid-123', $user->getAuthIdentifier());
    }

    // ── Escenario 2: nombre y departamento disponibles sin query a BD ─────────

    public function test_jwt_user_exposes_name_and_department_from_profile(): void
    {
        $user = new JwtUser([
            'id'         => 'uuid',
            'name'       => 'Ana García',
            'department' => 'Calidad',
        ]);

        $this->assertSame('Ana García', $user->name);
        $this->assertSame('Calidad', $user->department);
    }

    public function test_department_claim_extracted_from_jwt(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksService::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $middleware = app(JwtMiddleware::class);
        $method     = (new \ReflectionClass(JwtMiddleware::class))
            ->getMethod('validateAndExtractClaims');
        $method->setAccessible(true);

        $token  = $this->buildJwt($privatePem, $publicPem, department: 'Engineering');
        $claims = $method->invoke($middleware, $token);

        $this->assertSame('Engineering', $claims['department']);
    }

    public function test_department_is_null_when_claim_is_absent(): void
    {
        $user = new JwtUser([
            'id'   => 'uuid',
            'name' => 'Sin Departamento',
        ]);

        $this->assertNull($user->department);
    }

    public function test_jwt_user_exposes_scope(): void
    {
        $user = new JwtUser([
            'id'    => 'uuid',
            'scope' => 'openid profile email',
        ]);

        $this->assertSame('openid profile email', $user->scope);
    }

    public function test_jwt_user_scope_defaults_to_empty_string_when_absent(): void
    {
        $user = new JwtUser(['id' => 'uuid']);

        $this->assertSame('', $user->scope);
    }

    // ── Guard api (jwt-token): resuelve JwtUser desde atributos del request ──

    public function test_api_guard_resolves_jwt_user_from_request_attributes(): void
    {
        $profile = [
            'id'    => 'guard-test-uuid',
            'name'  => 'Guard User',
            'email' => 'guard@example.com',
            'scope' => 'openid',
        ];

        $request = Request::create('/api/v1/me', 'GET');
        $request->attributes->set('jwt_user', $profile);
        $this->app->instance('request', $request);

        $user = Auth::guard('api')->user();

        $this->assertInstanceOf(JwtUser::class, $user);
        $this->assertSame('guard-test-uuid', $user->id);
        $this->assertSame('Guard User', $user->name);
        $this->assertSame('openid', $user->scope);
    }

    public function test_api_guard_returns_null_without_request_attribute(): void
    {
        $request = Request::create('/api/v1/me', 'GET');
        $this->app->instance('request', $request);

        $this->assertNull(Auth::guard('api')->user());
    }

    // ── Escenario 3: WWW-Authenticate en respuestas 401 ──────────────────────

    public function test_missing_token_returns_www_authenticate_header(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');
    }

    public function test_expired_token_returns_www_authenticate_with_error(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();

        $this->mock(JwksService::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $token = $this->buildJwt($privatePem, $publicPem, expiredAt: true);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="api", error="invalid_token"');
    }

    public function test_invalid_token_returns_www_authenticate_with_error(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not.valid.token'])
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Bearer realm="api", error="invalid_token"');
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
        string $kid = 'test-kid',
        string $userId = 'user-test-uuid',
        ?string $department = null,
        bool $expiredAt = false,
    ): string {
        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($privatePem),
            InMemory::plainText($publicPem),
        );

        $now = new DateTimeImmutable();
        $exp = $expiredAt ? $now->modify('-1 hour') : $now->modify('+1 hour');

        $builder = $config->builder()
            ->issuedBy('test-issuer')
            ->permittedFor('test-audience')
            ->issuedAt($now->modify('-2 hours'))
            ->canOnlyBeUsedAfter($now->modify('-2 hours'))
            ->expiresAt($exp)
            ->relatedTo($userId)
            ->withClaim('email', 'test@example.com')
            ->withClaim('name', 'Test User')
            ->withHeader('kid', $kid);

        if ($department !== null) {
            $builder = $builder->withClaim('department', $department);
        }

        return $builder
            ->getToken(new Sha256(), InMemory::plainText($privatePem))
            ->toString();
    }
}
