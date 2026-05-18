<?php

namespace Tests\Feature;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Support\Facades\Cache;
use Maya\Auth\Contracts\JwksServiceInterface;
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

    private function mockRepositoryTree()
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
                                ['id' => 'M_MAT_1', 'name' => 'Matemáticas', 'study_id' => 'S_ESO_1']
                            ]
                        ]
                    ]
                ],
                [
                    'id' => 'ST_FP',
                    'name' => 'FP',
                    'studies' => []
                ]
            ]));

        $this->app->instance(AcademicHierarchyRepositoryInterface::class, $mockRepo);
    }

    private function mockUserProfile(array $profileOverrides = [])
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

    public function test_hierarchy_endpoint_returns_filtered_tree_by_study_type()
    {
        $this->mockRepositoryTree();
        $this->mockUserProfile([
            'study_type_ids' => ['ST_ESO'],
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 'ST_ESO');
        $response->assertJsonPath('data.0.studies.0.id', 'S_ESO_1');
    }

    public function test_hierarchy_endpoint_returns_full_tree_for_admin()
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

    public function test_hierarchy_endpoint_returns_empty_when_no_assignments()
    {
        $this->mockRepositoryTree();
        $this->mockUserProfile([
            'study_type_ids' => [],
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_hierarchy_endpoint_returns_full_tree_for_auditor()
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

    public function test_hierarchy_results_are_cached_in_redis()
    {
        $this->assertFalse(Cache::store('redis')->has('academic_hierarchy_tree'));

        $this->mockRepositoryTree();
        $this->mockUserProfile(['permissions' => ['admin']]);

        // First call populates cache
        $this->withHeaders($this->authHeaders())->getJson('/api/v1/hierarchy')->assertStatus(200);

        // Assert cache contains the data
        $this->assertTrue(Cache::store('redis')->has('academic_hierarchy_tree'));
        
        $cachedData = Cache::store('redis')->get('academic_hierarchy_tree');
        $this->assertIsArray($cachedData);
        $this->assertEquals('ST_ESO', $cachedData[0]['id']);
    }
}
