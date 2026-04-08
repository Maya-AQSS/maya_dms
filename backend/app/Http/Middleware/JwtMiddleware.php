<?php

namespace App\Http\Middleware;

use App\Services\JwksService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function __construct(
        private readonly JwksService $jwksService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            $this->logAuthFailure($request, null, 'Missing Authorization header');
            return response()->json(['error' => 'Unauthenticated'], 401, [
                'WWW-Authenticate' => 'Bearer realm="api"',
            ]);
        }

        try {
            $claims = $this->validateAndExtractClaims($token);
            $this->setCurrentUser($request, $claims);
        } catch (\Throwable $e) {
            $this->logAuthFailure($request, $token, $e->getMessage());
            return response()->json(['error' => 'Unauthenticated'], 401, [
                'WWW-Authenticate' => 'Bearer realm="api", error="invalid_token"',
            ]);
        }

        return $next($request);
    }

    private function logAuthFailure(Request $request, ?string $token, string $reason): void
    {
        Log::warning('JWT authentication failed', [
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'token_hint' => $token !== null ? substr($token, 0, 8) : null,
            'reason'     => $reason,
        ]);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

    private function validateAndExtractClaims(string $rawToken): array
    {
        // Extraer kid del header sin validar aún (necesitamos la clave primero)
        $parts = explode('.', $rawToken);

        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT');
        }

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $kid    = $header['kid'] ?? null;

        if ($kid === null) {
            throw new RuntimeException('JWT missing kid header');
        }

        $publicKey = $this->jwksService->getPublicKey($kid);

        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            \Lcobucci\JWT\Signer\Key\InMemory::plainText('verification-only'), // signing key no se usa en validación
            $publicKey,
        );

        $config->setValidationConstraints(
            new SignedWith(new Sha256(), $publicKey),
            new IssuedBy(config('auth.jwt_issuer')),
            new PermittedFor(config('auth.jwt_audience')),
            new StrictValidAt(new class implements ClockInterface {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                }
            }),
        );

        $token = $config->parser()->parse($rawToken);
        $config->validator()->assert($token, ...$config->validationConstraints());

        return $token->claims()->all();
    }

    /**
     * Construye el perfil del usuario a partir de los claims JWT y lo cachea en Redis (TTL 15 min).
     * El perfil se deposita en el atributo 'jwt_user' del request.
     * Auth::user() / $request->user() lo resuelven de forma diferida a través del guard
     * 'api' (jwt-token) registrado en AppServiceProvider::boot().
     */
    private function setCurrentUser(Request $request, array $claims): void
    {
        $userId = $claims['sub'] ?? null;

        if ($userId === null) {
            throw new RuntimeException('JWT missing sub claim');
        }

        $cacheKey = "jwt_user:{$userId}";

        $profile = Cache::remember($cacheKey, 900, function () use ($claims) {
            return [
                'id'              => $claims['sub'],
                'email'           => $claims['email'] ?? null,
                'name'            => $claims['name'] ?? null,
                'department'      => $claims['department'] ?? $claims['departamento'] ?? null,
                'organization_id' => $claims['organization_id'] ?? $claims['org_id'] ?? null,
                'roles'           => $claims['realm_access']['roles'] ?? [],
                'scope'           => $claims['scope'] ?? '',
            ];
        });

        $request->attributes->set('jwt_user', $profile);
    }
}
