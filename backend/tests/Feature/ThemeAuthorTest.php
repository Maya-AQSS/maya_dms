<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Cubre C3: el autor de los metadatos PDF es el usuario creador (snapshot del
 * nombre desde la vista FDW `users`), no es editable, y al clonar pasa a ser
 * el usuario que clona.
 */
class ThemeAuthorTest extends TestCase
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
    private function authHeaders(string $sub, array $codes = []): array
    {
        auth()->forgetUser();

        foreach (['theme.index', 'theme.show', 'theme.create', 'theme.update', 'theme.clone', 'theme.delete', 'dms.login'] as $slug) {
            if (! in_array($slug, $codes, true)) {
                $codes[] = $slug;
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

    private function seedUser(string $id, string $name): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'email' => substr($id, 0, 8).'@test.local',
            'is_active' => true,
        ]);
    }

    public function test_author_is_resolved_from_creator_name(): void
    {
        $userId = (string) Str::uuid();
        $this->seedUser($userId, 'Ana Pérez');
        $headers = $this->authHeaders($userId);

        $this->postJson('/api/v1/themes', ['name' => 'Tema'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.accessibility.author', 'Ana Pérez');
    }

    public function test_author_falls_back_to_ceedcv_when_user_not_resolvable(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());

        $this->postJson('/api/v1/themes', ['name' => 'Tema'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.accessibility.author', 'CEEDCV');
    }

    public function test_client_supplied_author_is_ignored_on_create(): void
    {
        $userId = (string) Str::uuid();
        $this->seedUser($userId, 'Ana Pérez');
        $headers = $this->authHeaders($userId);

        $this->postJson('/api/v1/themes', [
            'name' => 'Tema',
            'accessibility' => ['author' => 'IMPOSTOR'],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.accessibility.author', 'Ana Pérez');
    }

    public function test_author_is_immutable_on_update(): void
    {
        $userId = (string) Str::uuid();
        $this->seedUser($userId, 'Ana Pérez');
        $headers = $this->authHeaders($userId);

        $id = $this->postJson('/api/v1/themes', ['name' => 'Tema'], $headers)->json('data.id');

        $this->patchJson('/api/v1/themes/'.$id, [
            'accessibility' => ['language' => 'en', 'author' => 'IMPOSTOR'],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.accessibility.author', 'Ana Pérez')
            ->assertJsonPath('data.accessibility.language', 'en');
    }

    public function test_clone_sets_author_to_cloner(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        // Crea sin fila en `users` → autor del padre = 'CEEDCV'.
        $parent = $this->postJson('/api/v1/themes', ['name' => 'Padre'], $headers)->json('data');
        $this->assertSame('CEEDCV', $parent['accessibility']['author']);

        // Ahora el usuario sí tiene nombre resoluble; al clonar, el autor del
        // clon debe re-resolverse al del clonador (no heredar el del padre).
        $this->seedUser($userId, 'Ana Pérez');

        $this->postJson('/api/v1/themes/'.$parent['id'].'/clone', [], $headers)
            ->assertCreated()
            ->assertJsonPath('data.accessibility.author', 'Ana Pérez');
    }
}
