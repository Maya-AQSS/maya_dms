<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Models\TemplateVersion;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Modo secuencial vs paralelo en aprobación/rechazo por revisión.
 */
class DocumentReviewModeFlowTest extends TestCase
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
        $versionId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $blockSnapId = (string) Str::uuid();

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
            'version' => 1,
            'review_stages' => 2,
            'review_mode' => $reviewMode,
        ]);

        TemplateVersion::query()->forceCreate([
            'id' => $versionId,
            'template_id' => $templateId,
            'version_number' => 1,
            'blocks_snapshot' => [[
                'id' => $blockSnapId,
                'type' => 'heading',
                'title' => 'Bloque',
                'default_content' => null,
                'block_state' => 'editable',
                'mandatory' => false,
                'sort_order' => 0,
            ]],
            'changelog' => 'v1',
            'published_by' => $submitterId,
            'published_at' => now(),
        ]);

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
            'template_id' => $templateId,
            'template_version_id' => $versionId,
            'title' => 'Doc flujo',
            'study_id' => null,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        DocumentShare::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'user_id' => $submitterId,
            'permission' => 'read',
            'granted_by' => $ownerId,
        ]);

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
    private function bearerFor(string $sub, string $privatePem, string $publicPem, string $kidSuffix): array
    {
        auth()->forgetUser();

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.$kidSuffix,
            $sub,
            'test-issuer',
            'test-audience',
            [],
            [],
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
            ->andReturn(InMemory::plainText($pub));

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1');
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2');

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

    public function test_sequential_reject_higher_stage_is_blocked_until_lower_pending(): void
    {
        $ctx = $this->seedDocumentInReview('sequential');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($pub));

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1');
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2');

        $this->postJson("/api/v1/documents/{$ctx['documentId']}/submit", [], $hOwner)->assertOk();

        $list = $this->getJson("/api/v1/documents/{$ctx['documentId']}/reviews", $hR1)
            ->assertOk()
            ->json('data');
        $ids = $this->reviewIdsByStage($list);

        $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/reviews/{$ids['review2Id']}/reject",
            [],
            $hR2,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['review']);
    }

    public function test_parallel_allows_higher_stage_to_act_first(): void
    {
        $ctx = $this->seedDocumentInReview('parallel');
        [$priv, $pub] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($pub));

        $hOwner = $this->bearerFor($ctx['ownerId'], $priv, $pub, 'own');
        $hR1 = $this->bearerFor($ctx['rev1'], $priv, $pub, 'r1');
        $hR2 = $this->bearerFor($ctx['rev2'], $priv, $pub, 'r2');

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
}
