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

    public function test_users_search_returns_403_without_template_or_document_show_permission(): void
    {
        $userId = (string) Str::uuid();

        $response = $this->getJson('/api/v1/users?search=ab', $this->authHeaders($userId, ['document.create']));

        $response->assertForbidden();
    }

    public function test_users_search_returns_200_with_template_show_permission(): void
    {
        $userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => 'usr_search_target',
            'name' => 'Abigail Search',
            'email' => 'abigail.search@maya.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/users?search=ab', $this->authHeaders($userId, ['template.show']));

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
            $this->authHeaders($userId, ['document.create']), // Lacks documents.read
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
            'permission_slug' => 'document.review',
        ]);

        $response = $this->getJson(
            '/api/v1/users/document-reviewer-candidates?search=doc',
            $this->authHeaders($callerId, ['document.show']),
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
            'permission_slug' => 'document.review',
        ]);
        }

        $url = '/api/v1/users/document-reviewer-candidates?search=Reviewer&exclude_user_id='.urlencode($reviewerA);
        $response = $this->getJson($url, $this->authHeaders($callerId, ['document.show']));

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
            'permission_slug' => 'template.review',
        ]);
        }

        $url = '/api/v1/users/reviewer-candidates?search=Tpl&exclude_user_id='.urlencode($reviewerA);
        $response = $this->getJson($url, $this->authHeaders($callerId, ['template.show']));

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($reviewerA, $ids);
        $this->assertContains($reviewerB, $ids);
    }

    public function test_template_reviewer_candidates_filters_by_study_scope_without_downward_inclusion(): void
    {
        $callerId = (string) Str::uuid();
        $studyTypeId = (string) Str::uuid();
        $studyId = (string) Str::uuid();
        $moduleId = (string) Str::uuid();
        $studyReviewerId = (string) Str::uuid();
        $moduleOnlyReviewerId = (string) Str::uuid();
        $otherStudyReviewerId = (string) Str::uuid();

        DB::table('study_types')->insert([
            'id' => $studyTypeId,
            'name' => 'Grado test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('studies')->insert([
            'id' => $studyId,
            'study_type_id' => $studyTypeId,
            'name' => 'Medicina test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('course_modules')->insert([
            'id' => $moduleId,
            'study_id' => $studyId,
            'name' => 'Anatomía test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$studyReviewerId, $moduleOnlyReviewerId, $otherStudyReviewerId] as $rid) {
            DB::table('users')->insert([
                'id' => $rid,
                'name' => 'Reviewer '.$rid,
                'email' => 'scope-'.$rid.'@maya.test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_resolved_permissions')->insertOrIgnore([
                'user_id' => $rid,
                'permission_slug' => 'template.review',
            ]);
        }

        DB::table('user_studies')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $studyReviewerId,
            'study_id' => $studyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_course_modules')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $moduleOnlyReviewerId,
            'module_id' => $moduleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_studies')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $otherStudyReviewerId,
            'study_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = '/api/v1/users/reviewer-candidates?visibility_level=study&study_id='.urlencode($studyId);
        $response = $this->getJson($url, $this->authHeaders($callerId, ['template.show']));

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($studyReviewerId, $ids);
        $this->assertNotContains($moduleOnlyReviewerId, $ids);
        $this->assertNotContains($otherStudyReviewerId, $ids);
    }

    public function test_template_reviewer_candidates_module_scope_includes_parent_study_assignment(): void
    {
        $callerId = (string) Str::uuid();
        $studyTypeId = (string) Str::uuid();
        $studyId = (string) Str::uuid();
        $moduleId = (string) Str::uuid();
        $studyReviewerId = (string) Str::uuid();
        $moduleReviewerId = (string) Str::uuid();

        DB::table('study_types')->insert([
            'id' => $studyTypeId,
            'name' => 'Grado module scope',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('studies')->insert([
            'id' => $studyId,
            'study_type_id' => $studyTypeId,
            'name' => 'Estudio module scope',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('course_modules')->insert([
            'id' => $moduleId,
            'study_id' => $studyId,
            'name' => 'Módulo module scope',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$studyReviewerId, $moduleReviewerId] as $rid) {
            DB::table('users')->insert([
                'id' => $rid,
                'name' => 'Module scope '.$rid,
                'email' => 'mod-'.$rid.'@maya.test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_resolved_permissions')->insertOrIgnore([
                'user_id' => $rid,
                'permission_slug' => 'template.review',
            ]);
        }

        DB::table('user_studies')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $studyReviewerId,
            'study_id' => $studyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_course_modules')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $moduleReviewerId,
            'module_id' => $moduleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = '/api/v1/users/reviewer-candidates?visibility_level=module&module_id='.urlencode($moduleId);
        $response = $this->getJson($url, $this->authHeaders($callerId, ['template.show']));

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($studyReviewerId, $ids);
        $this->assertContains($moduleReviewerId, $ids);
    }
}
