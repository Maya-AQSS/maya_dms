<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Tests for TemplateReviewerAssignmentService validation branches exercised
 * via the syncReviewers (POST /templates/{id}/reviewers) and
 * syncDocumentReviewers (POST /templates/{id}/document-reviewers) endpoints.
 *
 * User IDs used here come from users_mock.php (seeded via UsersSourceSeeder):
 *   - OWNER_ID   = ed568442 (Dirección — has templates.read + templates.update)
 *   - REVIEWER_A = 2ead4bf3 (Secretaria — has templates.review + documents.review)
 *   - REVIEWER_B = f6bbe247 (Auditoría — has templates.review + documents.review)
 *   - NO_PERM    = 53bc5feb (Docente Bach — only templates.read, no review perms)
 */
class TemplateReviewerSyncValidationApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    // Fixed UUIDs from users_mock.php
    private const OWNER_ID   = 'ed568442-ece5-4c90-97ca-12c8969bb3a2';
    private const REVIEWER_A = '2ead4bf3-574c-41b4-95ca-cac7daed0664';
    private const REVIEWER_B = 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc';
    private const NO_PERM    = '53bc5feb-cf5a-4e0b-ba08-f7f21fe9ea8f';

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

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes = ['template.show', 'template.update']): array
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

    private function seedTemplate(
        string $ownerId,
        int $reviewStages = 0,
        string $reviewMode = 'parallel',
    ): string {
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Template Reviewer Sync Test',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => $reviewStages,
            'review_mode'      => $reviewMode,
        ]);

        return $templateId;
    }

    // ─── syncReviewers: duplicate user IDs (FormRequest distinct rule) ───────

    /**
     * When user_ids contains duplicates, the FormRequest 'distinct' rule fires
     * at the element level and returns 422 with 'user_ids.0' / 'user_ids.1' error keys.
     */
    public function test_sync_reviewers_with_duplicate_user_ids_returns_422(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID);

        // Same reviewerId appears twice → FormRequest distinct rule fires on elements
        $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
            'user_ids' => [self::REVIEWER_A, self::REVIEWER_A],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids.0']);
    }

    // ─── syncReviewers: sequential mode + review_stages limit exceeded ────────

    /**
     * When the template is in sequential mode with review_stages > 0 and the
     * number of unique reviewers exceeds that limit, the service throws a
     * ValidationException and the endpoint returns 422.
     */
    public function test_sync_reviewers_exceeding_sequential_stage_limit_returns_422(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID, reviewStages: 1, reviewMode: 'sequential');

        // Sending 2 reviewers but review_stages = 1 → limit exceeded
        $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
            'user_ids' => [self::REVIEWER_A, self::REVIEWER_B],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    }

    // ─── syncReviewers: user lacks permission ────────────────────────────────

    /**
     * When the reviewer candidate does not have templates.review permission,
     * assertUsersHavePermission throws a ValidationException → 422.
     */
    public function test_sync_reviewers_with_user_lacking_permission_returns_422(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID);

        // NO_PERM has no templates.review permission → service throws ValidationException
        $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
            'user_ids' => [self::NO_PERM],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    }

    // ─── syncDocumentReviewers: duplicate user IDs ───────────────────────────

    /**
     * When user_ids contains duplicates for document reviewers, the FormRequest
     * 'distinct' rule fires at the element level → 422 with 'user_ids.0' error key.
     */
    public function test_sync_document_reviewers_with_duplicate_user_ids_returns_422(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID);

        // Same reviewerId appears twice → FormRequest distinct rule fires on elements
        $this->postJson("/api/v1/templates/{$templateId}/document-reviewers", [
            'user_ids' => [self::REVIEWER_A, self::REVIEWER_A],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids.0']);
    }

    // ─── syncDocumentReviewers: user lacks permission ────────────────────────

    /**
     * When the document reviewer candidate does not have documents.review
     * permission, assertUsersHavePermission throws a ValidationException → 422.
     */
    public function test_sync_document_reviewers_with_user_lacking_permission_returns_422(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID);

        // NO_PERM has no documents.review permission → service throws ValidationException
        $this->postJson("/api/v1/templates/{$templateId}/document-reviewers", [
            'user_ids' => [self::NO_PERM],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    }

    // ─── Happy path (smoke tests) ─────────────────────────────────────────────

    /**
     * A valid sync with a reviewer who has the required permission succeeds.
     */
    public function test_sync_reviewers_happy_path_returns_200(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID);

        // REVIEWER_A already has templates.review from UserPermissionsSeeder
        $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
            'user_ids' => [self::REVIEWER_A],
        ], $headers)
            ->assertOk();
    }

    /**
     * A valid sync with a document reviewer who has the required permission succeeds.
     */
    public function test_sync_document_reviewers_happy_path_returns_200(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID);

        // REVIEWER_A already has documents.review from UserPermissionsSeeder
        $this->postJson("/api/v1/templates/{$templateId}/document-reviewers", [
            'user_ids' => [self::REVIEWER_A],
        ], $headers)
            ->assertOk();
    }

    /**
     * An empty user_ids list is valid and syncs an empty set.
     */
    public function test_sync_reviewers_with_empty_list_returns_200(): void
    {
        $headers    = $this->authHeaders(self::OWNER_ID);
        $templateId = $this->seedTemplate(self::OWNER_ID);

        $this->postJson("/api/v1/templates/{$templateId}/reviewers", [
            'user_ids' => [],
        ], $headers)
            ->assertOk();
    }
}
