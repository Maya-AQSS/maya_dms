<?php

namespace Tests\Feature;

use App\Models\CourseModule;
use App\Models\Study;
use App\Models\StudyType;
use App\Models\JwtUser;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AcademicHierarchyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::store('redis')->flush();
    }

    /**
     * Authenticate a mock user to bypass JWT middleware
     */
    private function authenticateFakeUser()
    {
        $payload = [
            'sub' => '12345',
            'preferred_username' => 'test_teacher',
            'email' => 'teacher@example.com',
            'given_name' => 'John',
            'family_name' => 'Doe',
            'roles' => ['docente']
        ];
        
        $this->withHeaders([
            'Authorization' => 'Bearer fake-token'
        ]);
        
        // This simulates what JwtMiddleware does
        $this->withoutMiddleware(\App\Http\Middleware\JwtMiddleware::class);
    }

    public function test_hierarchy_endpoint_returns_nested_json_tree()
    {
        $this->authenticateFakeUser();

        // Arrange database using Models
        $type = StudyType::create(['id' => 'ST_ESO', 'name' => 'Educación Secundaria Obligatoria']);
        $study = $type->studies()->create(['id' => 'S_ESO_1', 'name' => '1º ESO']);
        $study->courseModules()->create(['id' => 'M_MAT_1', 'name' => 'Matemáticas']);

        // Act
        $response = $this->getJson('/api/v1/hierarchy');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
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
                                'study_id'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response->assertJsonPath('0.id', 'ST_ESO');
        $response->assertJsonPath('0.studies.0.id', 'S_ESO_1');
        $response->assertJsonPath('0.studies.0.course_modules.0.id', 'M_MAT_1');
    }

    public function test_hierarchy_results_are_cached_in_redis()
    {
        $this->authenticateFakeUser();
        
        // Assert cache starts empty
        $this->assertFalse(Cache::store('redis')->has('academic_hierarchy_tree'));

        $type = StudyType::create(['id' => 'ST_FP', 'name' => 'FP']);
        $study = $type->studies()->create(['id' => 'S_DAW', 'name' => 'DAW']);
        $study->courseModules()->create(['id' => 'M_DWES', 'name' => 'DWES']);

        // First call populates cache
        $this->getJson('/api/v1/hierarchy')->assertStatus(200);

        // Assert cache contains the data
        $this->assertTrue(Cache::store('redis')->has('academic_hierarchy_tree'));
        
        $cachedData = Cache::store('redis')->get('academic_hierarchy_tree');
        $this->assertIsArray($cachedData);
        $this->assertEquals('ST_FP', $cachedData[0]['id']);
    }
}
