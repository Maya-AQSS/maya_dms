<?php

namespace Tests\Feature;

use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Mockery;

class AcademicHierarchyApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock 'redis' store with ArrayStore for testing environments
        Cache::extend('redis', function () {
            return Cache::repository(new \Illuminate\Cache\ArrayStore());
        });
        
        // Clear cache before each test
        Cache::store('redis')->flush();
    }

    private function authenticateFakeUser()
    {
        $this->withHeaders(['Authorization' => 'Bearer fake-token']);
        $this->withoutMiddleware(\Maya\Auth\Middleware\JwtMiddleware::class);
    }

    public function test_hierarchy_endpoint_returns_nested_json_tree()
    {
        $this->authenticateFakeUser();

        // Act: Mock database fully using the Repository interface
        $mockRepo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $mockRepo->shouldReceive('getTree')
            ->once()
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
                ]
            ]));

        $this->app->instance(AcademicHierarchyRepositoryInterface::class, $mockRepo);

        // Assert
        $response = $this->getJson('/api/v1/hierarchy');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'studies' => [
                        '*' => [
                            'id',
                            'name',
                            'study_type_id',
                            'course_modules' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'study_id',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonPath('data.0.id', 'ST_ESO');
        $response->assertJsonPath('data.0.studies.0.id', 'S_ESO_1');
        $response->assertJsonPath('data.0.studies.0.course_modules.0.id', 'M_MAT_1');
    }

    public function test_hierarchy_results_are_cached_in_redis()
    {
        $this->authenticateFakeUser();
        
        $this->assertFalse(Cache::store('redis')->has('academic_hierarchy_tree'));

        $mockRepo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $mockRepo->shouldReceive('getTree')
            ->once() // Especiyfing once() guarantees it is NOT queried twice
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([
                ['id' => 'ST_FP', 'name' => 'FP', 'studies' => []]
            ]));
        
        $this->app->instance(AcademicHierarchyRepositoryInterface::class, $mockRepo);

        // First call populates cache
        $this->getJson('/api/v1/hierarchy')->assertStatus(200);

        // Assert cache contains the data
        $this->assertTrue(Cache::store('redis')->has('academic_hierarchy_tree'));
        
        $cachedData = Cache::store('redis')->get('academic_hierarchy_tree');
        $this->assertIsArray($cachedData);
        $this->assertEquals('ST_FP', $cachedData[0]['id']);
    }
}
