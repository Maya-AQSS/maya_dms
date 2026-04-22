<?php

namespace Tests\Feature;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class UsersSearchApiTest extends TestCase
{
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->seed(PermissionsSeeder::class);
    }

    /**
     * @param  list<string>  $codes
     *
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes): array
    {
        auth()->forgetUser();

        $now = now();
        foreach ($codes as $code) {
            DB::table('user_permissions')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $sub,
                'permission_code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

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

    public function test_users_search_returns_403_without_users_search_permission(): void
    {
        $userId = (string) Str::uuid();

        $response = $this->getJson('/api/v1/users?search=ab', $this->authHeaders($userId, ['documents.create']));

        $response->assertForbidden();
    }

    public function test_users_search_returns_200_with_users_search_permission(): void
    {
        $userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => 'usr_search_target',
            'name' => 'Abigail Search',
            'email' => 'abigail.search@maya.test',
            'department' => 'Profesorado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/users?search=ab', $this->authHeaders($userId, ['users.search']));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function test_document_reviewer_candidates_returns_403_without_users_search_permission(): void
    {
        $userId = (string) Str::uuid();

        $response = $this->getJson(
            '/api/v1/users/document-reviewer-candidates',
            $this->authHeaders($userId, ['documents.create']),
        );

        $response->assertForbidden();
    }

    public function test_document_reviewer_candidates_returns_users_with_documents_review_permission(): void
    {
        $callerId = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $reviewerId,
            'name' => 'Doc Reviewer Candidate',
            'email' => 'doc.reviewer.candidate@maya.test',
            'department' => 'Secretaría',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $now = now();
        DB::table('user_permissions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $reviewerId,
            'permission_code' => 'documents.review',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->getJson(
            '/api/v1/users/document-reviewer-candidates?search=doc',
            $this->authHeaders($callerId, ['users.search']),
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $ids = array_column($data, 'id');
        $this->assertContains($reviewerId, $ids);
    }
}
