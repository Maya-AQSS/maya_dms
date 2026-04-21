<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
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
                'user_id' => $reviewerId,
                'stage' => 2,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/dashboard', $headers);

        $response->assertOk()
            ->assertJsonPath('data.stats', [])
            ->assertJsonPath('data.recent_documents', [])
            ->assertJsonCount(3, 'data.template_review_inbox')
            ->assertJsonPath('data.template_review_inbox.0.template_id', $urgentTemplateId)
            ->assertJsonPath('data.template_review_inbox.1.template_id', $normalTemplateId)
            ->assertJsonPath('data.template_review_inbox.2.template_id', $noDeadlineTemplateId);
    }
}
