<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentShare;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Support\TemplateHeadSnapshot;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Messaging\Publishers\AuditPublisher;
use Database\Seeders\PermissionsSeeder;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * Modo secuencial vs paralelo en aprobación/rechazo por revisión.
 */
class DocumentReviewModeFlowTest extends TestCase
{
    private function anyStudyId(): string
    {
        $existing = DB::table('studies')->value('id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $studyTypeId = (string) Str::uuid();
        $studyId = (string) Str::uuid();

        DB::table('study_types')->insertOrIgnore([
            'id' => $studyTypeId,
            'name' => 'Tipo test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('studies')->insertOrIgnore([
            'id' => $studyId,
            'study_type_id' => $studyTypeId,
            'name' => 'Estudio test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $studyId;
    }

    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;
    use SeedsTemplatePublicationAnchor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);
    }

    /**
     * @return array{
     *   templateId: string,
     *   documentId: string,
     *   submitterId: string,
     *   ownerId: string,
     *   rev1: string,
     *   rev2: string,
     * }
     */
    private function seedDocumentInReview(string $reviewMode): array
    {
        $submitterId = (string) Str::uuid();
        $ownerId = (string) Str::uuid();
        $rev1 = (string) Str::uuid();
        $rev2 = (string) Str::uuid();
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $blockSnapId = (string) Str::uuid();
        $studyId = $this->anyStudyId();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla revisión',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $submitterId,
            'status' => 'draft',
            'review_stages' => 2,
            'review_mode' => $reviewMode,
        ]);

        $anchor = $this->seedCanonicalPublicationForTemplate(
            $templateId,
            1,
            $submitterId,
            [[
                'id' => $blockSnapId,
                'title' => 'Bloque',
                'default_content' => null,
                'block_state' => 'optional',
                'sort_order' => 0,
                'type' => '',
                'mandatory' => false,
            ]],
            [
                'template' => [
                    'id' => $templateId,
                    'review_mode' => $reviewMode,
                ],
            ],
        );

        foreach ([[$rev1, 1], [$rev2, 2]] as [$uid, $stage]) {
            TemplateReviewer::query()->forceCreate([
                'id' => (string) Str::uuid(),
                'template_id' => $templateId,
                'user_id' => $uid,
                'stage' => $stage,
            ]);
        }

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => $anchor['entity_version_id'],
            'title' => 'Doc flujo',
            'study_id' => $studyId,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'draft',
        ]);

        DocumentShare::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'user_id' => $submitterId,
            'permission' => 'read',
            'granted_by' => $ownerId,
        ]);

        foreach ([$submitterId, $ownerId, $rev1, $rev2] as $uid) {
            DB::table('user_studies')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'user_id' => $uid,
                'study_id' => $studyId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'templateId' => $templateId,
            'documentId' => $documentId,
            'submitterId' => $submitterId,
            'ownerId' => $ownerId,
            'rev1' => $rev1,
            'rev2' => $rev2,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function bearerFor(string $sub, string $privatePem, string $publicPem, string $kidSuffix, array $permissions = []): array
    {
        auth()->forgetUser();

        if ($permissions !== []) {
            $this->assignUserPermissions($sub, $permissions);
        }

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.$kidSuffix,
            $sub,
            'test-issuer',
            'test-audience',
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @param  list<array<string, mixed>>  $reviews
     * @return array{review1Id: string, review2Id: string}
     */
    private function reviewIdsByStage(array $reviews): array
    {
        $byStage = [];
        foreach ($reviews as $row) {
            $byStage[(int) $row['stage']] = (string) $row['id'];
        }

        return [
            'review1Id' => $byStage[1],
            'review2Id' => $byStage[2],
        ];
    }

    public function test_sequential_blocks_higher_stage_until_lower_is_approved(): void
    {
        $ctx = $this->seedDocumentInReview('sequential');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1', ['documents.review']);
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $list = $this->getJson("/api/v1/documents/{$ctx['documentId']}/reviews", $hR1)
            ->assertOk()
            ->json('data');
        $ids = $this->reviewIdsByStage($list);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review2Id']}/approve",
            [],
            $hR2,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['review']);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review1Id']}/approve",
            [],
            $hR1,
        )->assertOk();

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review2Id']}/approve",
            [],
            $hR2,
        )->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_assigned_reviewer_can_view_in_review_document_without_academic_context(): void
    {
        $ownerId = (string) Str::uuid();
        $reviewerId = 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc';
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $studyId = $this->anyStudyId();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla doc in review',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $ownerId,
            'status' => 'published',
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc in review sin contexto',
            'study_id' => $studyId,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'in_review',
        ]);

        DocumentReview::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'reviewer_id' => $reviewerId,
            'status' => 'pending',
            'stage' => 1,
        ]);

        $this->assertDatabaseMissing('user_studies', [
            'user_id' => $reviewerId,
            'study_id' => $studyId,
        ]);

        [$priv, $pub] = $this->generateRsaKeyPairForTests();
        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $headersReviewer = $this->bearerFor(
            $reviewerId,
            $priv,
            $pub,
            'reviewer-no-context',
            ['documents.read', 'documents.review'],
        );

        $this->getJson("/api/v1/documents/{$documentId}", $headersReviewer)
            ->assertOk()
            ->assertJsonPath('data.id', $documentId)
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_sequential_reject_higher_stage_is_blocked_until_lower_pending(): void
    {
        $ctx = $this->seedDocumentInReview('sequential');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1', ['documents.review']);
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $list = $this->getJson("/api/v1/documents/{$ctx['documentId']}/reviews", $hR1)
            ->assertOk()
            ->json('data');
        $ids = $this->reviewIdsByStage($list);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review2Id']}/reject",
            ['rejection_reason' => 'Intento de rechazar fuera de orden'],
            $hR2,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['review']);
    }

    public function test_sequential_uses_anchored_template_review_mode_even_if_live_template_changes_to_parallel(): void
    {
        $ctx = $this->seedDocumentInReview('sequential');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $templateHead = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $ctx['templateId'])
            ->where('version_number', 0)
            ->firstOrFail();
        $templateHead->snapshot_data = TemplateHeadSnapshot::mergeTemplateKey(
            is_array($templateHead->snapshot_data) ? $templateHead->snapshot_data : [],
            ['review_mode' => 'parallel'],
        );
        $templateHead->save();

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1', ['documents.review']);
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $list = $this->getJson("/api/v1/documents/{$ctx['documentId']}/reviews", $hR1)
            ->assertOk()
            ->json('data');
        $ids = $this->reviewIdsByStage($list);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review2Id']}/approve",
            [],
            $hR2,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['review']);
    }

    public function test_reject_document_review_requires_rejection_reason(): void
    {
        $ctx = $this->seedDocumentInReview('parallel');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $list = $this->getJson("/api/v1/documents/{$ctx['documentId']}/reviews", $hR1)
            ->assertOk()
            ->json('data');
        $ids = $this->reviewIdsByStage($list);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review1Id']}/reject",
            [],
            $hR1,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_parallel_allows_higher_stage_to_act_first(): void
    {
        $ctx = $this->seedDocumentInReview('parallel');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1', ['documents.review']);
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $list = $this->getJson("/api/v1/documents/{$ctx['documentId']}/reviews", $hR1)
            ->assertOk()
            ->json('data');
        $ids = $this->reviewIdsByStage($list);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review2Id']}/approve",
            [],
            $hR2,
        )->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review1Id']}/approve",
            [],
            $hR1,
        )->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_parallel_reject_review_returns_document_to_draft(): void
    {
        $ctx = $this->seedDocumentInReview('parallel');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $this->getJson("/api/v1/documents/{$ctx['documentId']}/reviews", $hR1)
            ->assertOk();

        $review1Id = (string) DocumentReview::query()
            ->where('document_id', $ctx['documentId'])
            ->where('reviewer_id', $ctx['rev1'])
            ->value('id');
        $this->assertNotSame('', $review1Id);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$review1Id}/reject",
            ['rejection_reason' => 'No procede'],
            $hR1,
        )->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame(
            0,
            DocumentReview::query()
                ->where('document_id', $ctx['documentId'])
                ->where('status', 'pending')
                ->count(),
        );
    }

    public function test_approve_review_publishes_explicit_review_audit_event(): void
    {
        $ctx = $this->seedDocumentInReview('parallel');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $review2Id = (string) DocumentReview::query()
            ->where('document_id', $ctx['documentId'])
            ->where('reviewer_id', $ctx['rev2'])
            ->value('id');
        $this->assertNotSame('', $review2Id);

        $auditPublisher = $this->mock(AuditPublisher::class);
        $auditPublisher->shouldIgnoreMissing();
        $auditPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $applicationSlug,
                string $entityType,
                string $entityId,
                string $action,
                string $userId,
                ?string $blockId,
                ?array $previousValue,
                ?array $newValue,
            ) use ($ctx, $review2Id): bool {
                return $applicationSlug === 'maya-dms'
                    && $entityType === 'document'
                    && $entityId === $ctx['documentId']
                    && $action === 'review_approved'
                    && $userId === $ctx['rev2']
                    && ($previousValue['review_id'] ?? null) === $review2Id
                    && ($previousValue['status'] ?? null) === 'pending'
                    && ($newValue['review_id'] ?? null) === $review2Id
                    && ($newValue['status'] ?? null) === 'approved';
            });

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$review2Id}/approve",
            [],
            $hR2,
        )->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_reject_review_publishes_explicit_review_audit_event(): void
    {
        $ctx = $this->seedDocumentInReview('parallel');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($pub);

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1', ['documents.review']);

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $review1Id = (string) DocumentReview::query()
            ->where('document_id', $ctx['documentId'])
            ->where('reviewer_id', $ctx['rev1'])
            ->value('id');
        $this->assertNotSame('', $review1Id);

        $reason = 'No procede';
        $auditPublisher = $this->mock(AuditPublisher::class);
        $auditPublisher->shouldIgnoreMissing();
        $auditPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $applicationSlug,
                string $entityType,
                string $entityId,
                string $action,
                string $userId,
                ?string $blockId,
                ?array $previousValue,
                ?array $newValue,
            ) use ($ctx, $review1Id, $reason): bool {
                return $applicationSlug === 'maya-dms'
                    && $entityType === 'document'
                    && $entityId === $ctx['documentId']
                    && $action === 'review_rejected'
                    && $userId === $ctx['rev1']
                    && ($previousValue['review_id'] ?? null) === $review1Id
                    && ($previousValue['status'] ?? null) === 'pending'
                    && ($newValue['review_id'] ?? null) === $review1Id
                    && ($newValue['status'] ?? null) === 'rejected'
                    && ($newValue['rejection_reason'] ?? null) === $reason;
            });

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$review1Id}/reject",
            ['rejection_reason' => $reason],
            $hR1,
        )->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }
}
