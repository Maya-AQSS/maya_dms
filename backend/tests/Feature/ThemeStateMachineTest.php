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

/**
 * Cubre la máquina de estados de themes (C1): transiciones permitidas vía
 * endpoints dedicados y prohibición de editar `status` por PATCH.
 */
class ThemeStateMachineTest extends TestCase
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

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function createTheme(): array
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $id = $this->postJson('/api/v1/themes', ['name' => 'Tema'], $headers)
            ->assertCreated()
            ->json('data.id');

        return [$id, $headers];
    }

    public function test_new_theme_starts_in_draft(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->getJson('/api/v1/themes/'.$id, $headers)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_publish_transitions_draft_to_published(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->postJson('/api/v1/themes/'.$id.'/publish', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_publish_is_idempotent(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->postJson('/api/v1/themes/'.$id.'/publish', [], $headers)->assertOk();
        $this->postJson('/api/v1/themes/'.$id.'/publish', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_archive_transitions_published_to_archived(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->postJson('/api/v1/themes/'.$id.'/publish', [], $headers)->assertOk();
        $this->postJson('/api/v1/themes/'.$id.'/archive', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_cannot_archive_a_draft_directly(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->postJson('/api/v1/themes/'.$id.'/archive', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_publish_an_archived_theme(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->postJson('/api/v1/themes/'.$id.'/publish', [], $headers)->assertOk();
        $this->postJson('/api/v1/themes/'.$id.'/archive', [], $headers)->assertOk();

        $this->postJson('/api/v1/themes/'.$id.'/publish', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_patch_with_status_is_rejected(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->patchJson('/api/v1/themes/'.$id, ['status' => 'published'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        // El estado real no cambió.
        $this->getJson('/api/v1/themes/'.$id, $headers)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_patch_without_status_still_works(): void
    {
        [$id, $headers] = $this->createTheme();

        $this->patchJson('/api/v1/themes/'.$id, ['name' => 'Nuevo nombre'], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Nuevo nombre');
    }
}
