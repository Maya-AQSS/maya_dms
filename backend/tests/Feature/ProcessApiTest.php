<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class ProcessApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes = ['template.show']): array
    {
        auth()->forgetUser();

        $this->assignUserPermissions($sub, $codes);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($sub, 0, 8),
            $sub,
            'test-issuer',
            'test-audience',
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    private function insertProcess(
        string $code,
        string $name,
        string $alias,
        ?string $parentId = null,
    ): string {
        $id = (string) Str::uuid();

        DB::table('processes')->insert([
            'id'                => $id,
            'code'              => $code,
            'name'              => $name,
            'alias'             => $alias,
            'description'       => null,
            'process_parent_id' => $parentId,
        ]);

        return $id;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/processes')
            ->assertUnauthorized();
    }

    public function test_index_returns_empty_array_when_no_processes_except_default(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        // Only the default process inserted by TestCase::setUp() exists
        $response = $this->getJson('/api/v1/processes', $headers)
            ->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data)); // default process always present
    }

    public function test_index_returns_processes_with_correct_shape(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->insertProcess('PA01', 'Proceso Administración', 'admin');

        $response = $this->getJson('/api/v1/processes', $headers)
            ->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Find PA01 in results
        $pa01 = collect($data)->firstWhere('code', 'PA01');
        $this->assertNotNull($pa01, 'PA01 process must appear in index');
        $this->assertArrayHasKey('id', $pa01);
        $this->assertArrayHasKey('code', $pa01);
        $this->assertArrayHasKey('name', $pa01);
        $this->assertArrayHasKey('alias', $pa01);
        $this->assertArrayHasKey('description', $pa01);
        $this->assertArrayHasKey('process_parent_id', $pa01);
        $this->assertSame('PA01', $pa01['code']);
        $this->assertSame('Proceso Administración', $pa01['name']);
        $this->assertSame('admin', $pa01['alias']);
        $this->assertNull($pa01['description']);
        $this->assertNull($pa01['process_parent_id']);
    }

    public function test_index_returns_multiple_processes_ordered_by_code(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        // Insert out-of-order to verify ordering
        $this->insertProcess('PZ99', 'Proceso Z', 'z');
        $this->insertProcess('PA01', 'Proceso A', 'a');
        $this->insertProcess('PM05', 'Proceso M', 'm');

        $response = $this->getJson('/api/v1/processes', $headers)
            ->assertOk();

        $data    = $response->json('data');
        $codes   = collect($data)->pluck('code')->values()->all();
        $indexed = array_values(array_filter($codes, fn (string $c) => in_array($c, ['PA01', 'PM05', 'PZ99'])));

        $this->assertSame(['PA01', 'PM05', 'PZ99'], $indexed);
    }

    public function test_index_returns_sub_processes_with_parent_id(): void
    {
        $userId    = (string) Str::uuid();
        $headers   = $this->authHeaders($userId);

        $parentId = $this->insertProcess('PE01', 'Proceso Educación', 'edu');
        $childId  = $this->insertProcess('PE01.01', 'Sub-proceso Educación Primaria', 'edu-primaria', $parentId);

        $response = $this->getJson('/api/v1/processes', $headers)
            ->assertOk();

        $data   = $response->json('data');
        $child  = collect($data)->firstWhere('id', $childId);

        $this->assertNotNull($child);
        $this->assertSame($parentId, $child['process_parent_id']);
    }

    public function test_index_returns_200_for_any_authenticated_user(): void
    {
        // No specific permission is required for listing processes —
        // the JWT middleware is enough.
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId, []);  // no permissions

        $this->getJson('/api/v1/processes', $headers)
            ->assertOk();
    }
}
