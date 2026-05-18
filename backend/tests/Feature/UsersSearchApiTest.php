<?php

namespace Tests\Feature;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            DB::table('user_resolved_permissions')->insertOrIgnore([
            'user_id' => $sub,
            'permission_slug' => $code,
        ]);
        }

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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/users?search=ab', $this->authHeaders($userId, ['users.search']));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function test_document_reviewer_candidates_returns_403_without_documents_read_permission(): void
    {
        $userId = (string) Str::uuid();

        $response = $this->getJson(
            '/api/v1/users/document-reviewer-candidates',
            $this->authHeaders($userId, ['documents.create']), // Lacks documents.read
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $now = now();
        DB::table('user_resolved_permissions')->insertOrIgnore([
            'user_id' => $reviewerId,
            'permission_slug' => 'documents.review',
        ]);

        $response = $this->getJson(
            '/api/v1/users/document-reviewer-candidates?search=doc',
            $this->authHeaders($callerId, ['documents.read']),
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $ids = array_column($data, 'id');
        $this->assertContains($reviewerId, $ids);
    }

    public function test_document_reviewer_candidates_exclude_user_id_omits_user(): void
    {
        $callerId = (string) Str::uuid();
        $reviewerA = (string) Str::uuid();
        $reviewerB = (string) Str::uuid();

        foreach ([$reviewerA, $reviewerB] as $rid) {
            DB::table('users')->insert([
                'id' => $rid,
                'name' => 'Reviewer '.$rid,
                'email' => 'r-'.$rid.'@maya.test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_resolved_permissions')->insertOrIgnore([
            'user_id' => $rid,
            'permission_slug' => 'documents.review',
        ]);
        }

        $url = '/api/v1/users/document-reviewer-candidates?search=Reviewer&exclude_user_id='.urlencode($reviewerA);
        $response = $this->getJson($url, $this->authHeaders($callerId, ['documents.read']));

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($reviewerA, $ids);
        $this->assertContains($reviewerB, $ids);
    }

    public function test_template_reviewer_candidates_exclude_user_id_omits_user(): void
    {
        $callerId = (string) Str::uuid();
        $reviewerA = (string) Str::uuid();
        $reviewerB = (string) Str::uuid();

        foreach ([$reviewerA, $reviewerB] as $rid) {
            DB::table('users')->insert([
                'id' => $rid,
                'name' => 'Tpl Reviewer '.$rid,
                'email' => 't-'.$rid.'@maya.test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_resolved_permissions')->insertOrIgnore([
            'user_id' => $rid,
            'permission_slug' => 'templates.review',
        ]);
        }

        $url = '/api/v1/users/reviewer-candidates?search=Tpl&exclude_user_id='.urlencode($reviewerA);
        $response = $this->getJson($url, $this->authHeaders($callerId, ['templates.read']));

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($reviewerA, $ids);
        $this->assertContains($reviewerB, $ids);
    }
}
