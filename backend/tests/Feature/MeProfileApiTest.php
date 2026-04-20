<?php

namespace Tests\Feature;

use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Contrato JSON de GET /api/v1/me (perfil sin roles ni organización en el cuerpo).
 */
class MeProfileApiTest extends TestCase
{
    use BuildsTestJwt;

    private const SUB = 'usr_me_contract_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-me-contract',
            self::SUB,
            'test-issuer',
            'test-audience',
            ['docente'],
            ['department' => 'Calidad'],
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_me_response_has_permissions_and_department_not_roles_or_org(): void
    {
        $expectedProfile = [
            'id'             => self::SUB,
            'email'          => 'me.contract@test.local',
            'name'           => 'Contrato Me',
            'department'     => 'Ingeniería',
            'study_type_ids' => [],
            'study_ids'      => [],
            'module_ids'     => [],
            'team_ids'       => [],
            'permissions'    => ['templates.read', 'documents.create'],
            'teams'          => [],
            'source'         => 'fdw',
        ];

        $this->mock(UserProfileServiceInterface::class)
            ->shouldReceive('getProfile')
            ->once()
            ->withArgs(function (string $userId, array $jwtProfile): bool {
                return $userId === self::SUB
                    && ($jwtProfile['id'] ?? null) === self::SUB;
            })
            ->andReturn($expectedProfile);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data', $expectedProfile);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('roles', $data);
        $this->assertArrayHasKey('permissions', $data);
        $this->assertArrayHasKey('department', $data);
    }
}
