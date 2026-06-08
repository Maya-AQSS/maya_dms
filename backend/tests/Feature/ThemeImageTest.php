<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Cubre C2: imágenes como capas del layout. Subida de imagen (archivo o URL
 * con guarda anti-SSRF), validación del path de `props.src`, inyección de
 * `srcUrl` firmado en la respuesta y no-persistencia de campos derivados.
 */
class ThemeImageTest extends TestCase
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
        Storage::fake('media');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $sub): array
    {
        auth()->forgetUser();

        $codes = ['theme.index', 'theme.show', 'theme.create', 'theme.update', 'theme.clone', 'theme.delete', 'dms.login'];
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

    private function createTheme(array $headers): string
    {
        return $this->postJson('/api/v1/themes', ['name' => 'Tema'], $headers)
            ->assertCreated()
            ->json('data.id');
    }

    public function test_upload_image_file_returns_src_and_signed_url(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $id = $this->createTheme($headers);

        $response = $this->post(
            '/api/v1/themes/'.$id.'/images',
            ['file' => UploadedFile::fake()->image('logo.png', 100, 100)],
            $headers,
        )->assertCreated();

        $src = $response->json('data.src');
        $this->assertMatchesRegularExpression('#^themes/[a-f0-9\-]{36}/[a-f0-9\-]{36}$#', $src);
        $this->assertNotNull($response->json('data.url'));
        $this->assertStringContainsString('ct=theme', $response->json('data.url'));
        Storage::disk('media')->assertExists($src);
    }

    public function test_upload_requires_file_or_url(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $id = $this->createTheme($headers);

        $this->postJson('/api/v1/themes/'.$id.'/images', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'url']);
    }

    public function test_ingest_from_url_rejects_loopback_address(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $id = $this->createTheme($headers);

        $this->postJson('/api/v1/themes/'.$id.'/images', [
            'url' => 'http://127.0.0.1/evil.png',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_ingest_from_url_rejects_private_address(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $id = $this->createTheme($headers);

        $this->postJson('/api/v1/themes/'.$id.'/images', [
            'url' => 'http://192.168.1.10/internal.png',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_create_rejects_invalid_image_src_path(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());

        $this->postJson('/api/v1/themes', [
            'name' => 'Tema',
            'layout' => [
                'regions' => [
                    ['id' => 'i1', 'type' => 'image', 'grid' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 4, 'z' => 1],
                        'props' => ['src' => '../../etc/passwd', 'alt' => 'x']],
                ],
            ],
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['layout.regions.0.props.src']);
    }

    public function test_image_block_gets_signed_src_url_on_show(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $themeId = (string) Str::uuid();
        $fileId = (string) Str::uuid();
        $src = "themes/{$themeId}/{$fileId}";

        $id = $this->postJson('/api/v1/themes', [
            'name' => 'Tema',
            'layout' => [
                'regions' => [
                    ['id' => 'i1', 'type' => 'image', 'grid' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 4, 'z' => 1],
                        'props' => ['src' => $src, 'alt' => 'Logo', 'opacity' => 0.5]],
                ],
                'page' => ['size' => 'A4', 'margin_cm' => ['top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 2]],
            ],
        ], $headers)->assertCreated()->json('data.id');

        $show = $this->getJson('/api/v1/themes/'.$id, $headers)->assertOk();

        $this->assertSame($src, $show->json('data.layout.regions.0.props.src'));
        $srcUrl = $show->json('data.layout.regions.0.props.srcUrl');
        $this->assertNotNull($srcUrl);
        $this->assertStringContainsString('ct=theme', $srcUrl);
        $this->assertStringContainsString('ci='.$themeId, $srcUrl);
    }

    public function test_derived_src_url_is_not_persisted(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $themeId = (string) Str::uuid();
        $fileId = (string) Str::uuid();
        $src = "themes/{$themeId}/{$fileId}";

        $id = $this->postJson('/api/v1/themes', [
            'name' => 'Tema',
            'layout' => [
                'regions' => [
                    ['id' => 'i1', 'type' => 'image', 'grid' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 4, 'z' => 1],
                        'props' => ['src' => $src, 'alt' => 'Logo', 'srcUrl' => 'https://forged.example/evil']],
                ],
                'page' => ['size' => 'A4', 'margin_cm' => ['top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 2]],
            ],
        ], $headers)->assertCreated()->json('data.id');

        // En BD el layout NO debe contener el srcUrl forjado (campo derivado).
        $rawLayout = DB::table('themes')->where('id', $id)->value('layout');
        $this->assertStringNotContainsString('forged.example', (string) $rawLayout);
    }
}
