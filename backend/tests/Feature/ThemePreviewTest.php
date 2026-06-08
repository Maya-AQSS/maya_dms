<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Cubre C4: paso de Verificación — preview HTML (lorem ipsum + paged.js) y PDF
 * de muestra del theme.
 */
class ThemePreviewTest extends TestCase
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

    private function createThemeWithContentSlot(array $headers): string
    {
        return $this->postJson('/api/v1/themes', [
            'name' => 'Tema verificación',
            'palette' => ['primary' => '#123abc'],
            'layout' => [
                'regions' => [
                    ['id' => 'cs', 'type' => 'content_slot', 'grid' => ['x' => 1, 'y' => 4, 'w' => 10, 'h' => 44, 'z' => 1]],
                ],
                'page' => ['size' => 'A4', 'margin_cm' => ['top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 2]],
            ],
        ], $headers)->assertCreated()->json('data.id');
    }

    public function test_preview_returns_themed_html_with_lorem_ipsum(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $id = $this->createThemeWithContentSlot($headers);

        $response = $this->get('/api/v1/themes/'.$id.'/preview', $headers)
            ->assertOk();

        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $response->assertSee('Lorem ipsum', false);
        // La paleta del theme se aplica (color primario en las CSS vars).
        $response->assertSee('#123abc', false);
    }

    public function test_preview_without_content_slot_has_no_lorem(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());
        $id = $this->postJson('/api/v1/themes', ['name' => 'Sin contenido'], $headers)
            ->assertCreated()->json('data.id');

        $this->get('/api/v1/themes/'.$id.'/preview', $headers)
            ->assertOk()
            ->assertDontSee('Lorem ipsum', false);
    }

    public function test_preview_not_found_for_missing_theme(): void
    {
        $headers = $this->authHeaders((string) Str::uuid());

        $this->get('/api/v1/themes/'.Str::uuid().'/preview', $headers)
            ->assertNotFound();
    }

    public function test_sample_pdf_streams_pdf_bytes(): void
    {
        Process::fake([
            '*' => Process::result(output: '%PDF-1.7 sample bytes'),
        ]);

        $headers = $this->authHeaders((string) Str::uuid());
        $id = $this->createThemeWithContentSlot($headers);

        $response = $this->get('/api/v1/themes/'.$id.'/sample-pdf', $headers)
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        // El cuerpo es la salida (fake) de WeasyPrint → prueba que el endpoint
        // streamea los bytes del proceso sin tocar disco.
        $this->assertStringContainsString('%PDF-1.7 sample bytes', $response->getContent());
    }
}
