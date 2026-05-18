<?php

namespace Tests\Feature;

use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Support\Facades\Cache;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Contrato JSON de GET /api/v1/me (forma canónica cross-app, snake_case en
 * inglés):
 *
 *  - permissions: list<string>
 *  - study_type_ids, study_ids, module_ids, team_ids: list<string>
 *  - teams: list<{id,name,role,...}> (objetos completos, opcional)
 *
 * NO presentes en /me (eliminados):
 *  - roles, department/departamento, organizacion_id/organization_id, source.
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
            ->andReturn($publicPem);

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

    public function test_me_response_uses_canonical_snake_case_keys_without_legacy_fields(): void
    {
        // El Service interno devuelve el shape canónico directamente (los
        // nombres internos coinciden con la forma cross-app). El resolver
        // solo filtra los campos sobrantes.
        $serviceProfile = [
            'id'             => self::SUB,
            'email'          => 'me.contract@test.local',
            'name'           => 'Contrato Me',
            'department'     => 'Ingeniería',
            'study_type_ids' => ['ST_ESO'],
            'study_ids'      => ['STU_FOO'],
            'module_ids'     => ['MOD_BAR'],
            'team_ids'       => ['T1'],
            'permissions'    => ['templates.read', 'documents.create'],
            'teams'          => [['id' => 'T1', 'name' => 'Equipo Calidad', 'role' => 'member', 'is_department' => false]],
            'source'         => 'fdw',
        ];

        $this->mock(UserProfileServiceInterface::class)
            ->shouldReceive('getProfile')
            ->once()
            ->withArgs(function (string $userId, array $jwtProfile): bool {
                return $userId === self::SUB
                    && ($jwtProfile['id'] ?? null) === self::SUB;
            })
            ->andReturn($serviceProfile);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/me');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Campos canónicos presentes y con valores correctos.
        $this->assertSame(['templates.read', 'documents.create'], $data['permissions']);
        $this->assertSame(['ST_ESO'], $data['study_type_ids']);
        $this->assertSame(['STU_FOO'], $data['study_ids']);
        $this->assertSame(['MOD_BAR'], $data['module_ids']);
        $this->assertSame(['T1'], $data['team_ids']);
        $this->assertSame(
            [['id' => 'T1', 'name' => 'Equipo Calidad', 'role' => 'member', 'is_department' => false]],
            $data['teams'],
        );

        // Campos legacy / sobrantes: NO deben estar en el payload público.
        foreach (['roles', 'department', 'departamento', 'organization_id', 'organizacion_id', 'source'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $data, "No debería existir «{$forbidden}» en /me");
        }
    }
}
