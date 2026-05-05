<?php
 
namespace Tests\Feature;
 
use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\Template;
use App\Models\TemplateVersion;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;
 
class DashboardApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    private function anyStudyId(): string
    {
        $existing = \DB::table('studies')->value('id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $studyTypeId = (string) Str::uuid();
        $studyId = (string) Str::uuid();

        \DB::table('study_types')->insertOrIgnore([
            'id' => $studyTypeId,
            'name' => 'Tipo test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('studies')->insertOrIgnore([
            'id' => $studyId,
            'study_type_id' => $studyTypeId,
            'name' => 'Estudio test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $studyId;
    }
 
    protected function setUp(): void
    {
        parent::setUp();
 
        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);
 
        Cache::flush();
 
        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }
 
    /**
     * @return array<string, string>
     */
    private function authHeaders(string $sub): array
    {
        auth()->forgetUser();
 
        $this->assignUserPermissions($sub, ['templates.read']);
 
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
 
    public function test_dashboard_returns_pending_template_review_inbox_sorted_by_deadline(): void
    {
        $reviewerId = (string) Str::uuid();
        $otherReviewerId = (string) Str::uuid();
        $headers = $this->authHeaders($reviewerId);
 
        $urgentTemplateId = (string) Str::uuid();
        $normalTemplateId = (string) Str::uuid();
        $noDeadlineTemplateId = (string) Str::uuid();
        $ignoredTemplateId = (string) Str::uuid();
 
        Template::query()->forceCreate([
            'id' => $urgentTemplateId,
            'name' => 'Urgente',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDays(1),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'in_review',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);
 
        Template::query()->forceCreate([
            'id' => $normalTemplateId,
            'name' => 'Normal',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDays(5),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'in_review',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);
 
        Template::query()->forceCreate([
            'id' => $noDeadlineTemplateId,
            'name' => 'Sin fecha',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'in_review',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);
 
        Template::query()->forceCreate([
            'id' => $ignoredTemplateId,
            'name' => 'Ignorada por estado',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDay(),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);
 
        \DB::table('template_reviewers')->insert([
            [
                'id' => (string) Str::uuid(),
                'template_id' => $urgentTemplateId,
                'user_id' => $reviewerId,
                'stage' => 1,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'template_id' => $normalTemplateId,
                'user_id' => $reviewerId,
                'stage' => 1,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'template_id' => $noDeadlineTemplateId,
                'user_id' => $reviewerId,
                'stage' => 1,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'template_id' => $ignoredTemplateId,
                'user_id' => $reviewerId,
                'stage' => 1,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'template_id' => $urgentTemplateId,
                'user_id' => $otherReviewerId,
                'stage' => 2,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'template_id' => $normalTemplateId,
                'user_id' => $otherReviewerId,
                'stage' => 2,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
 
        $response = $this->getJson('/api/v1/dashboard', $headers);
 
        $response->assertOk()
            ->assertJsonPath('data.stats.documents_critical', 0)
            ->assertJsonPath('data.stats.documents_high', 0)
            ->assertJsonPath('data.stats.templates_critical', 2)
            ->assertJsonPath('data.stats.templates_high', 0)
            ->assertJsonPath('data.recent_documents', [])
            ->assertJsonCount(0, 'data.document_review_inbox')
            ->assertJsonCount(3, 'data.template_review_inbox')
            ->assertJsonPath('data.template_review_inbox.0.template_id', $urgentTemplateId)
            ->assertJsonPath('data.template_review_inbox.1.template_id', $normalTemplateId)
            ->assertJsonPath('data.template_review_inbox.2.template_id', $noDeadlineTemplateId);
    }

    public function test_dashboard_document_review_inbox_respects_sequential_stage(): void
    {
        $rev1 = (string) Str::uuid();
        $rev2 = (string) Str::uuid();
        $ownerId = (string) Str::uuid();
        $headers = $this->authHeaders($rev1);

        $templateId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $blockSnapId = (string) Str::uuid();
        $studyId = $this->anyStudyId();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla doc inbox',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $ownerId,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 2,
            'review_mode' => 'sequential',
        ]);

        TemplateVersion::query()->forceCreate([
            'id' => $versionId,
            'template_id' => $templateId,
            'version_number' => 1,
            'blocks_snapshot' => [[
                'id' => $blockSnapId,
                'title' => 'B',
                'default_content' => null,
                'block_state' => 'optional',
                'sort_order' => 0,
            ]],
            'changelog' => 'v1',
            'published_by' => $ownerId,
            'published_at' => now(),
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'template_version_id' => $versionId,
            'title' => 'Programación en revisión',
            'study_type_id' => null,
            'study_id' => $studyId,
            'module_id' => null,
            'delivery_deadline' => now()->addDays(3),
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'in_review',
            'current_version' => 1,
            'submitted_at' => now(),
            'published_at' => null,
        ]);

        DocumentReview::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'reviewer_id' => $rev1,
            'stage' => 1,
            'status' => 'pending',
        ]);
        DocumentReview::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'reviewer_id' => $rev2,
            'stage' => 2,
            'status' => 'pending',
        ]);

        foreach ([$rev1, $rev2] as $uid) {
            \DB::table('user_studies')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'user_id' => $uid,
                'study_id' => $studyId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $r1 = $this->getJson('/api/v1/dashboard', $headers);
        $r1->assertOk()
            ->assertJsonPath('data.stats.documents_critical', 1)
            ->assertJsonPath('data.stats.documents_high', 0)
            ->assertJsonPath('data.stats.templates_critical', 0)
            ->assertJsonPath('data.stats.templates_high', 0)
            ->assertJsonCount(1, 'data.document_review_inbox')
            ->assertJsonPath('data.document_review_inbox.0.document_id', $documentId)
            ->assertJsonPath('data.document_review_inbox.0.review_stage', 1);

        auth()->forgetUser();
        $headersRev2 = $this->authHeaders($rev2);
        $r2 = $this->getJson('/api/v1/dashboard', $headersRev2);
        $r2->assertOk()
            ->assertJsonPath('data.stats.documents_critical', 0)
            ->assertJsonPath('data.stats.documents_high', 0)
            ->assertJsonPath('data.stats.templates_critical', 0)
            ->assertJsonPath('data.stats.templates_high', 0)
            ->assertJsonCount(0, 'data.document_review_inbox');
    }

    public function test_dashboard_template_review_inbox_respects_sequential_stage(): void
    {
        $rev1 = (string) Str::uuid();
        $rev2 = (string) Str::uuid();
        $headersRev2 = $this->authHeaders($rev2);

        $templateId = (string) Str::uuid();
        $ownerId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla secuencial dashboard',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => now()->addDays(2),
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $ownerId,
            'status' => 'in_review',
            'version' => 1,
            'review_stages' => 2,
            'review_mode' => 'sequential',
        ]);

        \DB::table('template_reviewers')->insert([
            [
                'id' => (string) Str::uuid(),
                'template_id' => $templateId,
                'user_id' => $rev1,
                'stage' => 1,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'template_id' => $templateId,
                'user_id' => $rev2,
                'stage' => 2,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $blocked = $this->getJson('/api/v1/dashboard', $headersRev2);
        $blocked->assertOk()
            ->assertJsonCount(0, 'data.template_review_inbox')
            ->assertJsonPath('data.stats.templates_critical', 0)
            ->assertJsonPath('data.stats.templates_high', 0);

        \DB::table('template_reviewers')
            ->where('template_id', $templateId)
            ->where('user_id', $rev1)
            ->update(['status' => 'approved', 'updated_at' => now()]);

        $unblocked = $this->getJson('/api/v1/dashboard', $headersRev2);
        $unblocked->assertOk()
            ->assertJsonCount(1, 'data.template_review_inbox')
            ->assertJsonPath('data.template_review_inbox.0.template_id', $templateId)
            ->assertJsonPath('data.template_review_inbox.0.review_stage', 2);
    }
}