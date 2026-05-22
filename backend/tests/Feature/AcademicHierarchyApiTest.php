<?php

namespace Tests\Feature;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Support\Facades\Cache;
use Maya\Auth\Contracts\JwksServiceInterface;
use Maya\Profile\Dtos\AcademicContextDto;
use Maya\Profile\Dtos\AcademicItemDto;
use Maya\Profile\Dtos\StudyDto;
use Maya\Profile\Services\Contracts\AcademicContextServiceInterface;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;
use Mockery;

class AcademicHierarchyApiTest extends TestCase
{
    use BuildsTestJwt;

    private const SUB = 'test_user_hierarchy';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::extend('redis', function () {
            return Cache::repository(new \Illuminate\Cache\ArrayStore());
        });

        Cache::store('redis')->flush();
    }

    private function authHeaders(): array
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-hierarchy',
            self::SUB,
            'test-issuer',
            'test-audience',
            [],
            []
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    private function mockRepositoryTree(): void
    {
        $mockRepo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $mockRepo->shouldReceive('getTree')
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([
                [
                    'id' => 'ST_ESO',
                    'name' => 'Educación Secundaria Obligatoria',
                    'studies' => [
                        [
                            'id' => 'S_ESO_1',
                            'name' => '1º ESO',
                            'study_type_id' => 'ST_ESO',
                            'course_modules' => [
                                ['id' => 'M_MAT_1', 'name' => 'Matemáticas', 'study_id' => 'S_ESO_1'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'ST_FP',
                    'name' => 'FP',
                    'studies' => [],
                ],
            ]));

        $this->app->instance(AcademicHierarchyRepositoryInterface::class, $mockRepo);
    }

    private function mockUserProfile(array $profileOverrides = []): void
    {
        $defaultProfile = [
            'id'             => self::SUB,
            'study_type_ids' => [],
            'study_ids'      => [],
            'module_ids'     => [],
            'permissions'    => [],
        ];

        $profile = array_merge($defaultProfile, $profileOverrides);

        $this->mock(UserProfileServiceInterface::class)
            ->shouldReceive('getProfile')
            ->withArgs(function (string $userId) {
                return $userId === self::SUB;
            })
            ->andReturn($profile);
    }

    /**
     * @param  list<AcademicItemDto>  $studyTypes
     * @param  list<StudyDto>  $studies
     * @param  list<AcademicItemDto>  $modules
     */
    private function mockAcademicContext(
        array $studyTypes = [],
        array $studies = [],
        array $modules = [],
    ): void {
        $context = new AcademicContextDto(
            studyTypes: $studyTypes,
            studies: $studies,
            modules: $modules,
            teams: [],
            status: [
                'study_types' => 'ok',
                'studies' => 'ok',
                'modules' => 'ok',
                'teams' => 'ok',
            ],
        );

        $this->mock(AcademicContextServiceInterface::class)
            ->shouldReceive('forUser')
            ->with(self::SUB)
            ->andReturn($context);
    }

    public function test_hierarchy_endpoint_returns_tree_from_user_academic_context(): void
    {
        $this->mockUserProfile([
            'study_type_ids' => ['ST_ESO'],
        ]);
        $this->mockAcademicContext(
            studyTypes: [
                new AcademicItemDto('ST_ESO', 'ST_ESO', 'Educación Secundaria Obligatoria'),
            ],
            studies: [
                new StudyDto('S_ESO_1', 'S_ESO_1', '1º ESO', 'ST_ESO'),
            ],
        );

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 'ST_ESO');
        $response->assertJsonPath('data.0.studies.0.id', 'S_ESO_1');
    }

    public function test_hierarchy_endpoint_returns_full_tree_for_admin(): void
    {
        $this->mockRepositoryTree();
        $this->mockUserProfile([
            'permissions' => ['admin'],
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', 'ST_ESO');
        $response->assertJsonPath('data.1.id', 'ST_FP');
    }

    public function test_hierarchy_endpoint_returns_empty_when_user_has_no_academic_context(): void
    {
        $this->mockUserProfile([
            'study_type_ids' => [],
        ]);
        $this->mockAcademicContext();

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_hierarchy_endpoint_returns_full_tree_for_auditor(): void
    {
        $this->mockRepositoryTree();
        $this->mockUserProfile([
            'permissions' => ['audit.read'],
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', 'ST_ESO');
        $response->assertJsonPath('data.1.id', 'ST_FP');
    }

    public function test_hierarchy_results_are_cached_in_redis(): void
    {
        $this->assertFalse(Cache::store('redis')->has('academic_hierarchy_tree'));

        $this->mockRepositoryTree();
        $this->mockUserProfile(['permissions' => ['admin']]);

        $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy')->assertStatus(200);

        $this->assertTrue(Cache::store('redis')->has('academic_hierarchy_tree'));

        $cachedData = Cache::store('redis')->get('academic_hierarchy_tree');
        $this->assertIsArray($cachedData);
        $this->assertEquals('ST_ESO', $cachedData[0]['id']);
    }
}
