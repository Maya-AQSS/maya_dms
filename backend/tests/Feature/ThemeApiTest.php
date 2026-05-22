<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class ThemeApiTest extends TestCase
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
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes = [], bool $withThemeCatalog = true): array
    {
        auth()->forgetUser();

        if ($withThemeCatalog) {
            foreach (['theme.index', 'theme.show', 'theme.create', 'theme.update', 'theme.clone', 'theme.delete', 'dms.login'] as $slug) {
                if (! in_array($slug, $codes, true)) {
                    $codes[] = $slug;
                }
            }
        }

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

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/themes')->assertUnauthorized();
    }

    public function test_index_forbidden_without_theme_index(): void
    {
        $headers = $this->authHeaders((string) Str::uuid(), ['dms.login'], withThemeCatalog: false);

        $this->getJson('/api/v1/themes', $headers)->assertForbidden();
    }

    public function test_index_returns_empty_collection_when_no_themes(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());

        $this->getJson('/api/v1/themes', $headers)
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']])
            ->assertJsonCount(0, 'data');
    }

    public function test_store_creates_theme_with_defaults(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $payload = [
            'name' => 'Tema CEEDCV',
            'description' => 'Tema corporativo principal',
            'palette' => ['primary' => '#123456'],
        ];

        $response = $this->postJson('/api/v1/themes', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tema CEEDCV')
            ->assertJsonPath('data.created_by', $userId)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.palette.primary', '#123456')
            ->assertJsonPath('data.palette.secondary', '#666666'); // default kept

        $this->assertNotNull($response->json('data.id'));
        $this->assertArrayHasKey('typography', $response->json('data'));
        $this->assertArrayHasKey('accessibility', $response->json('data'));
    }

    public function test_show_returns_theme_by_id(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $id = $this->postJson('/api/v1/themes', ['name' => 'X'], $headers)
            ->json('data.id');

        $this->getJson('/api/v1/themes/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'X');
    }

    public function test_update_modifies_only_provided_fields(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $created = $this->postJson('/api/v1/themes', ['name' => 'Orig'], $headers)->json('data');
        $id = $created['id'];

        $this->patchJson('/api/v1/themes/'.$id, [
            'name' => 'Renombrado',
            'palette' => ['primary' => '#abcdef'],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Renombrado')
            ->assertJsonPath('data.palette.primary', '#abcdef');
    }

    public function test_update_forbidden_for_non_owner(): void
    {
        // Comprobamos la lógica de Policy directamente para evitar la colisión
        // de mocks JWT que ocurre al re-stubear JwksServiceInterface dentro de
        // un mismo test (limitación de Tests\Concerns\BuildsTestJwt).
        $owner = (string) Str::uuid();
        $stranger = (string) Str::uuid();

        $theme = new \App\Models\Theme;
        $theme->id = (string) Str::uuid();
        $theme->name = 'Owned';
        $theme->created_by = $owner;
        $theme->palette = ['primary' => '#000', 'secondary' => '#000', 'text' => '#000', 'background' => '#fff'];
        $theme->typography = ['heading_font' => 'sans', 'body_font' => 'sans', 'base_size_pt' => 11, 'line_height' => 1.5];
        $theme->layout = ['regions' => [], 'page' => ['size' => 'A4']];
        $theme->assets = ['logo_path' => null, 'background_image_path' => null, 'watermark_path' => null];
        $theme->accessibility = ['language' => 'es', 'title' => null, 'subject' => null, 'author' => 'CEEDCV'];
        $theme->save();

        $policy = new \App\Policies\ThemePolicy;
        $ownerUser = new \App\Models\JwtUser([
            'id' => $owner,
            'sub' => $owner,
            'permissions' => ['theme.show', 'theme.create'],
        ]);
        $strangerUser = new \App\Models\JwtUser([
            'id' => $stranger,
            'sub' => $stranger,
            'permissions' => ['theme.show'],
        ]);
        $editorUser = new \App\Models\JwtUser([
            'id' => $stranger,
            'sub' => $stranger,
            'permissions' => ['theme.show', 'theme.update'],
        ]);

        $this->assertTrue($policy->update($ownerUser, $theme));
        $this->assertFalse($policy->update($strangerUser, $theme));
        $this->assertTrue($policy->update($editorUser, $theme));
        $adminUser = new \App\Models\JwtUser([
            'id' => $stranger,
            'sub' => $stranger,
            'permissions' => ['theme.show', 'theme.delete'],
        ]);

        $this->assertTrue($policy->delete($ownerUser, $theme));
        $this->assertFalse($policy->delete($strangerUser, $theme));
        $this->assertTrue($policy->delete($adminUser, $theme));
    }

    public function test_delete_removes_theme(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $id = $this->postJson('/api/v1/themes', ['name' => 'ToDelete'], $headers)->json('data.id');

        $this->deleteJson('/api/v1/themes/'.$id, [], $headers)
            ->assertNoContent();

        $this->getJson('/api/v1/themes/'.$id, $headers)
            ->assertNotFound();
    }

    // ─── Clone ────────────────────────────────────────────────────────────────

    public function test_clone_creates_copy_with_overrides_and_marks_parent(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $parent = $this->postJson('/api/v1/themes', [
            'name' => 'Padre',
            'palette' => ['primary' => '#0b5394', 'secondary' => '#555555'],
        ], $headers)->json('data');

        $cloned = $this->postJson('/api/v1/themes/'.$parent['id'].'/clone', [
            'name' => 'Padre — variante roja',
            'palette' => ['primary' => '#cc0000'],
        ], $headers)->assertCreated()->json('data');

        $this->assertNotSame($parent['id'], $cloned['id']);
        $this->assertSame($parent['id'], $cloned['cloned_from_id']);
        $this->assertSame($userId, $cloned['created_by']);
        $this->assertSame('#cc0000', $cloned['palette']['primary']);
        // Override sólo de primary: secondary debe heredarse del padre.
        $this->assertSame('#555555', $cloned['palette']['secondary']);
        $this->assertSame('draft', $cloned['status']);
    }

    public function test_clone_uses_default_name_when_not_provided(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $parent = $this->postJson('/api/v1/themes', ['name' => 'Base'], $headers)->json('data');

        $cloned = $this->postJson('/api/v1/themes/'.$parent['id'].'/clone', [], $headers)
            ->assertCreated()
            ->json('data');

        $this->assertSame('Base (copia)', $cloned['name']);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function test_store_rejects_invalid_color_hex(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());

        $this->postJson('/api/v1/themes', [
            'name' => 'Bad',
            'palette' => ['primary' => 'not-a-color'],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['palette.primary']);
    }

    public function test_store_requires_name(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());

        $this->postJson('/api/v1/themes', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
