<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ThemeImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * DMS-B10b: los SVG de tema se sanean con un allowlist (enshrined/svg-sanitize)
 * y se persiste la versión limpia, no el original. Sustituye al blocklist previo.
 */
final class ThemeSvgSanitizeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ThemeImageService
    {
        return app(ThemeImageService::class);
    }

    public function test_malicious_svg_is_stored_sanitized(): void
    {
        Storage::fake('media');

        $malicious = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            .'<script>alert(1)</script>'
            .'<rect width="10" height="10" fill="#000" onload="alert(2)"/>'
            .'<image href="http://evil.example/x.png"/>'
            .'</svg>';

        $dto = $this->service()->upload('theme-1', UploadedFile::fake()->createWithContent('logo.svg', $malicious));

        $stored = Storage::disk('media')->get($dto->src);
        $this->assertIsString($stored);
        // Vectores XSS eliminados por el allowlist.
        $this->assertStringNotContainsStringIgnoringCase('<script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onload', $stored);
        $this->assertStringNotContainsStringIgnoringCase('evil.example', $stored);
        // Contenido legítimo conservado.
        $this->assertStringContainsStringIgnoringCase('<rect', $stored);
    }

    public function test_clean_svg_round_trips(): void
    {
        Storage::fake('media');

        $clean = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20">'
            .'<circle cx="10" cy="10" r="8" fill="#1abc9c"/>'
            .'</svg>';

        $dto = $this->service()->upload('theme-2', UploadedFile::fake()->createWithContent('ok.svg', $clean));

        $stored = Storage::disk('media')->get($dto->src);
        $this->assertStringContainsStringIgnoringCase('<circle', $stored);
        $this->assertStringContainsStringIgnoringCase('#1abc9c', $stored);
    }

    public function test_non_svg_content_is_persisted_unchanged(): void
    {
        Storage::fake('media');

        $png = UploadedFile::fake()->image('logo.png', 4, 4);
        $original = $png->getContent();

        $dto = $this->service()->upload('theme-3', $png);

        $this->assertSame($original, Storage::disk('media')->get($dto->src));
    }
}
