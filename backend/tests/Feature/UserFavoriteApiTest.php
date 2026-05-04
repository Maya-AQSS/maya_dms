<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
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

class UserFavoriteApiTest extends TestCase
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
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes = ['templates.read', 'documents.read']): array
    {
        auth()->forgetUser();

        $this->assignUserPermissions($sub, $codes);

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
            [],
            [],
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_list_favorites_empty_for_new_user(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->getJson('/api/v1/favorites', $headers)
            ->assertOk()
            ->assertJsonPath('data.template_ids', [])
            ->assertJsonPath('data.document_ids', []);
    }

    public function test_template_favorite_add_list_remove(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['templates.read']);

        $create = $this->postJson('/api/v1/templates', [
            'name' => 'Fav test',
            'description' => null,
            'delivery_deadline' => now()->addDay()->toDateString(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
        ], $headers);

        $create->assertCreated();
        $templateId = $create->json('data.id');
        $this->assertIsString($templateId);

        $this->getJson('/api/v1/favorites', $headers)
            ->assertJsonPath('data.template_ids', []);

        $this->postJson("/api/v1/favorites/templates/{$templateId}", [], $headers)
            ->assertNoContent();

        $this->getJson('/api/v1/favorites', $headers)
            ->assertJsonPath('data.template_ids.0', $templateId)
            ->assertJsonPath('data.document_ids', []);

        $this->postJson("/api/v1/favorites/templates/{$templateId}", [], $headers)
            ->assertNoContent();

        $this->deleteJson("/api/v1/favorites/templates/{$templateId}", [], $headers)
            ->assertNoContent();

        $this->getJson('/api/v1/favorites', $headers)
            ->assertJsonPath('data.template_ids', []);
    }

    public function test_template_favorite_returns_404_for_unknown_id(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['templates.read']);
        $fakeId = (string) Str::uuid();

        $this->postJson("/api/v1/favorites/templates/{$fakeId}", [], $headers)
            ->assertNotFound();
    }

    public function test_document_favorite_add_list_remove(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId, ['templates.read', 'documents.read']);

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Doc fav',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $userId,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc fav',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $userId,
            'owner_id' => $userId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        $this->postJson("/api/v1/favorites/documents/{$documentId}", [], $headers)
            ->assertNoContent();

        $this->getJson('/api/v1/favorites', $headers)
            ->assertJsonPath('data.document_ids.0', $documentId);

        $this->deleteJson("/api/v1/favorites/documents/{$documentId}", [], $headers)
            ->assertNoContent();

        $this->getJson('/api/v1/favorites', $headers)
            ->assertJsonPath('data.document_ids', []);
    }
}
