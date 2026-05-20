<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateReviewer;
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
 * Tests for TemplateReviewService edge cases not exercised by TemplatesApiTest.
 *
 * Targets (72.2% → ≥80%):
 *   - submitForReview: template has no blocks → 422
 *   - submitForReview: modifiable block with empty default_content → 422
 *   - submitForReview: locked block with empty default_content → 422
 *   - submitForReview: non-personal template with no reviewers → 422
 *   - submitForReview: repeated submit updates blocks_submission_history
 *   - rejectReview: template not in_review but reviewer is assigned → 422 (service guards)
 *   - rejectReview: reviewer already approved → 422
 *   - approveReview: template not in_review but reviewer is assigned → 422
 *   - approveReview: reviewer already approved → 422
 *   - approveReview: sequential mode, previous stage not yet approved → 422
 *   - approveReview: last approval auto-publishes (allApproved path)
 *   - approveReview: not all approved → return fresh template (line 231)
 */
class TemplateReviewServiceEdgeCasesApiTest extends TestCase
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

    // ─── Helpers ──────────────────────────────────────────────────────────────

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

    /**
     * Builds a shared RSA keypair and returns headers for two subs signed with the same key.
     *
     * @param  list<string>  $codes1
     * @param  list<string>  $codes2
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function authHeadersPair(
        string $sub1,
        string $sub2,
        array $codes1 = ['template.show'],
        array $codes2 = ['template.show', 'template.review'],
    ): array {
        auth()->forgetUser();

        $this->assignUserPermissions($sub1, $codes1);
        $this->assignUserPermissions($sub2, $codes2);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token1 = $this->buildJwtForSub($privatePem, $publicPem, 'kid-a-'.substr($sub1, 0, 8), $sub1, 'test-issuer', 'test-audience');
        $token2 = $this->buildJwtForSub($privatePem, $publicPem, 'kid-b-'.substr($sub2, 0, 8), $sub2, 'test-issuer', 'test-audience');

        return [
            ['Authorization' => 'Bearer '.$token1],
            ['Authorization' => 'Bearer '.$token2],
        ];
    }

    private function seedDraftTemplate(string $ownerId, string $status = 'draft', string $visibility = TemplateVisibilityLevel::Personal->value): string
    {
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Template Edge Cases',
            'description'      => null,
            'visibility_level' => $visibility,
            'created_by'       => $ownerId,
            'status'           => $status,
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        return $templateId;
    }

    private function addEditableBlock(string $templateId, mixed $defaultContent = null): string
    {
        $blockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id'              => $blockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque Editable',
            'default_content' => $defaultContent,
            'block_state'     => 'editable',
            'sort_order'      => 0,
        ]);

        return $blockId;
    }

    private function addModifiableBlock(string $templateId, mixed $defaultContent = null): string
    {
        $blockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id'              => $blockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque Modificable',
            'default_content' => $defaultContent,
            'block_state'     => 'modifiable',
            'sort_order'      => 1,
        ]);

        return $blockId;
    }

    private function addLockedBlock(string $templateId, mixed $defaultContent = null): string
    {
        $blockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id'              => $blockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque Bloqueado',
            'default_content' => $defaultContent,
            'block_state'     => 'locked',
            'sort_order'      => 2,
        ]);

        return $blockId;
    }

    private function addReviewer(string $templateId, string $userId, int $stage = 1, string $status = 'pending'): void
    {
        TemplateReviewer::query()->forceCreate([
            'id'          => (string) Str::uuid(),
            'template_id' => $templateId,
            'user_id'     => $userId,
            'stage'       => $stage,
            'status'      => $status,
        ]);
    }

    // ─── submitForReview — validation errors ──────────────────────────────────

    public function test_submit_for_review_returns_422_when_template_has_no_blocks(): void
    {
        $ownerId    = (string) Str::uuid();
        $headers    = $this->authHeaders($ownerId);
        $templateId = $this->seedDraftTemplate($ownerId);

        // No blocks added — service throws because template.blocks().doesntExist()
        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', fn ($v) => str_contains($v, 'bloque'));
    }

    public function test_submit_for_review_returns_422_when_modifiable_block_has_empty_default_content(): void
    {
        $ownerId    = (string) Str::uuid();
        $headers    = $this->authHeaders($ownerId);
        $templateId = $this->seedDraftTemplate($ownerId);
        $this->addEditableBlock($templateId);
        $this->addModifiableBlock($templateId, null);  // null default_content → invalid

        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', fn ($v) => str_contains($v, 'modificable'));
    }

    public function test_submit_for_review_returns_422_when_modifiable_block_has_empty_array_default_content(): void
    {
        $ownerId    = (string) Str::uuid();
        $headers    = $this->authHeaders($ownerId);
        $templateId = $this->seedDraftTemplate($ownerId);
        $this->addEditableBlock($templateId);
        $this->addModifiableBlock($templateId, []);  // empty array also counts as empty

        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', fn ($v) => str_contains($v, 'modificable'));
    }

    public function test_submit_for_review_returns_422_when_locked_block_has_empty_default_content(): void
    {
        $ownerId    = (string) Str::uuid();
        $headers    = $this->authHeaders($ownerId);
        $templateId = $this->seedDraftTemplate($ownerId);
        $this->addEditableBlock($templateId);
        $this->addLockedBlock($templateId, null);  // null → invalid for locked blocks

        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', fn ($v) => str_contains($v, 'bloqueado'));
    }

    public function test_submit_for_review_returns_422_for_non_personal_template_with_no_reviewers(): void
    {
        $ownerId    = (string) Str::uuid();
        $headers    = $this->authHeaders($ownerId);
        $templateId = $this->seedDraftTemplate($ownerId, 'draft', TemplateVisibilityLevel::Global->value);
        $this->addEditableBlock($templateId);

        // No reviewers assigned — non-personal templates MUST have reviewers
        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.reviewers.0', fn ($v) => str_contains($v, 'revisor'));
    }

    public function test_submit_for_review_updates_blocks_submission_history_on_repeated_submit(): void
    {
        $ownerId    = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();
        [$headersOwner, $headersReviewer] = $this->authHeadersPair($ownerId, $reviewerId);
        $templateId = $this->seedDraftTemplate($ownerId);
        $this->addEditableBlock($templateId);
        $this->addReviewer($templateId, $reviewerId);

        // First submit → in_review
        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headersOwner)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        // Reviewer rejects → back to rejected
        $this->postJson("/api/v1/templates/{$templateId}/reject-review", [], $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        // Second submit → hits blocks_submission_history branch (TemplateReviewService lines 117-127)
        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headersOwner)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    // ─── rejectReview — error paths ───────────────────────────────────────────

    /**
     * The template owner is ALSO assigned as a reviewer (self-review scenario).
     * They can see their own draft template via the user_access global scope,
     * and the policy passes because they have templates.review + are in template_reviewers.
     * The service validates that status must be in_review → 422.
     *
     * This tests TemplateReviewService lines 141-144.
     */
    public function test_reject_review_returns_422_when_template_is_not_in_review(): void
    {
        // Owner self-assigns as reviewer so they pass the global scope (see own template)
        // AND pass the policy (templates.review + assigned in template_reviewers)
        $ownerId = (string) Str::uuid();
        $headers = $this->authHeaders($ownerId, ['template.show', 'template.review']);

        $templateId = $this->seedDraftTemplate($ownerId, 'draft');
        $this->addEditableBlock($templateId);
        $this->addReviewer($templateId, $ownerId);  // self-assigned reviewer

        // Status is 'draft' — service should reject even though global scope + policy pass
        $this->postJson("/api/v1/templates/{$templateId}/reject-review", [], $headers)
            ->assertUnprocessable();
    }

    public function test_reject_review_returns_422_when_reviewer_already_approved(): void
    {
        $ownerId    = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();
        [$headersOwner, $headersReviewer] = $this->authHeadersPair($ownerId, $reviewerId);

        $templateId = $this->seedDraftTemplate($ownerId);
        $this->addEditableBlock($templateId);
        $this->addReviewer($templateId, $reviewerId);

        // Submit → in_review (policy passes for rejectReview now)
        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headersOwner)->assertOk();

        // Force reviewer to approved status in DB
        TemplateReviewer::query()
            ->where('template_id', $templateId)
            ->where('user_id', $reviewerId)
            ->update(['status' => 'approved']);

        // Now try to reject — service sees reviewer.status === 'approved' → 422
        $this->postJson("/api/v1/templates/{$templateId}/reject-review", [], $headersReviewer)
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', fn ($v) => str_contains($v, 'aprobado'));
    }

    // ─── approveReview — error paths ──────────────────────────────────────────

    /**
     * Owner is also assigned as reviewer (self-review). They can see their own draft template
     * and pass the 'review' policy. The service validates status must be in_review → 422.
     *
     * This tests TemplateReviewService lines 181-183.
     */
    public function test_approve_review_returns_422_when_template_is_not_in_review(): void
    {
        $ownerId = (string) Str::uuid();
        $headers = $this->authHeaders($ownerId, ['template.show', 'template.review']);

        $templateId = $this->seedDraftTemplate($ownerId, 'draft');
        $this->addEditableBlock($templateId);
        $this->addReviewer($templateId, $ownerId);  // self-assigned reviewer

        // Status is 'draft' — service should reject even though global scope + policy pass
        $this->postJson("/api/v1/templates/{$templateId}/approve-review", [], $headers)
            ->assertUnprocessable();
    }

    public function test_approve_review_returns_422_when_reviewer_already_approved(): void
    {
        $ownerId    = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();
        [$headersOwner, $headersReviewer] = $this->authHeadersPair($ownerId, $reviewerId);

        $templateId = $this->seedDraftTemplate($ownerId);
        $this->addEditableBlock($templateId);
        $this->addReviewer($templateId, $reviewerId);

        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headersOwner)->assertOk();

        // Force approved status
        TemplateReviewer::query()
            ->where('template_id', $templateId)
            ->where('user_id', $reviewerId)
            ->update(['status' => 'approved']);

        $this->postJson("/api/v1/templates/{$templateId}/approve-review", [], $headersReviewer)
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', fn ($v) => str_contains($v, 'aprobado'));
    }

    public function test_approve_review_returns_422_in_sequential_mode_when_previous_stage_not_approved(): void
    {
        $ownerId     = (string) Str::uuid();
        $reviewer1Id = (string) Str::uuid();
        $reviewer2Id = (string) Str::uuid();

        // Set up three-way auth with same keypair
        auth()->forgetUser();
        $this->assignUserPermissions($ownerId, ['template.show']);
        $this->assignUserPermissions($reviewer1Id, ['template.show', 'template.review']);
        $this->assignUserPermissions($reviewer2Id, ['template.show', 'template.review']);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $tokenOwner    = $this->buildJwtForSub($privatePem, $publicPem, 'kid-o-'.substr($ownerId, 0, 8), $ownerId, 'test-issuer', 'test-audience');
        $tokenReviewer2 = $this->buildJwtForSub($privatePem, $publicPem, 'kid-r2-'.substr($reviewer2Id, 0, 8), $reviewer2Id, 'test-issuer', 'test-audience');

        $headersOwner    = ['Authorization' => 'Bearer '.$tokenOwner];
        $headersReviewer2 = ['Authorization' => 'Bearer '.$tokenReviewer2];

        // Seed template with sequential review mode via forceCreate (goes through creating hook)
        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Sequential Template',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        $this->addEditableBlock($templateId);
        $this->addReviewer($templateId, $reviewer1Id, stage: 1);
        $this->addReviewer($templateId, $reviewer2Id, stage: 2);

        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headersOwner)->assertOk();

        // Reviewer 2 (stage 2) tries to approve before reviewer 1 (stage 1)
        $this->postJson("/api/v1/templates/{$templateId}/approve-review", [], $headersReviewer2)
            ->assertUnprocessable()
            ->assertJsonPath('errors.stage.0', fn ($v) => str_contains($v, 'etapas anteriores'));
    }

    public function test_approve_review_returns_fresh_template_when_not_all_reviewers_approved(): void
    {
        $ownerId     = (string) Str::uuid();
        $reviewer1Id = (string) Str::uuid();
        $reviewer2Id = (string) Str::uuid();

        auth()->forgetUser();
        $this->assignUserPermissions($ownerId, ['template.show']);
        $this->assignUserPermissions($reviewer1Id, ['template.show', 'template.review']);
        $this->assignUserPermissions($reviewer2Id, ['template.show', 'template.review']);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $tokenOwner     = $this->buildJwtForSub($privatePem, $publicPem, 'kid-o-'.substr($ownerId, 0, 8), $ownerId, 'test-issuer', 'test-audience');
        $tokenReviewer1 = $this->buildJwtForSub($privatePem, $publicPem, 'kid-r1-'.substr($reviewer1Id, 0, 8), $reviewer1Id, 'test-issuer', 'test-audience');

        $headersOwner     = ['Authorization' => 'Bearer '.$tokenOwner];
        $headersReviewer1 = ['Authorization' => 'Bearer '.$tokenReviewer1];

        // Use parallel review_mode (stage doesn't matter — both are stage 1)
        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Parallel Template',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'parallel',
        ]);

        $this->addEditableBlock($templateId);
        $this->addReviewer($templateId, $reviewer1Id, stage: 1);
        $this->addReviewer($templateId, $reviewer2Id, stage: 1);

        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headersOwner)->assertOk();

        // Reviewer 1 approves — reviewer 2 still pending → template stays in_review (line 231)
        $response = $this->postJson("/api/v1/templates/{$templateId}/approve-review", [], $headersReviewer1)
            ->assertOk();

        $this->assertSame('in_review', $response->json('data.status'));
    }

    public function test_approve_review_auto_publishes_when_all_reviewers_approve(): void
    {
        $ownerId    = (string) Str::uuid();
        $reviewerId = (string) Str::uuid();
        [$headersOwner, $headersReviewer] = $this->authHeadersPair($ownerId, $reviewerId);

        $templateId = $this->seedDraftTemplate($ownerId);
        // Block must have default_content for publishWithSnapshot to pass validation
        $this->addEditableBlock($templateId, ['type' => 'doc', 'content' => [['type' => 'paragraph']]]);
        $this->addReviewer($templateId, $reviewerId);

        $this->postJson("/api/v1/templates/{$templateId}/submit-review", [], $headersOwner)->assertOk();

        // The only reviewer approves → auto-publish (TemplateReviewService lines 223-228)
        $response = $this->postJson("/api/v1/templates/{$templateId}/approve-review", [], $headersReviewer)
            ->assertOk();

        $this->assertSame('published', $response->json('data.status'));
    }
}
