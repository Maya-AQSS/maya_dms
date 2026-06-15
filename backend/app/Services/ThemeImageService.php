<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Media\UploadedMediaDto;
use App\Services\Contracts\ThemeImageServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ThemeImageService extends MediaUploadService implements ThemeImageServiceInterface
{
    protected function scopePrefix(): string
    {
        return 'themes';
    }

    // upload() se hereda de MediaUploadService (themes admite SVG, validado por
    // el FormRequest + content-type en la ingesta remota).

    /**
     * Rechaza SVGs con vectores XSS activos (script tags, event handlers, javascript:
     * URIs, foreignObject embebido). El MediaController además sirve cualquier SVG
     * como attachment con CSP estricta, pero esta capa evita persistir contenido
     * malicioso. PNG/JPEG/WEBP pasan sin inspección — los magic bytes los validó
     * el FormRequest.
     *
     * @throws ValidationException
     */
    protected function validateContent(string $content): void
    {
        $head = ltrim(substr($content, 0, 512));
        $isSvg = $head !== ''
            && (str_starts_with($head, '<?xml')
                || stripos($head, '<svg') !== false);

        if (! $isSvg) {
            return;
        }

        $lower = strtolower($content);
        $dangerous = [
            '<script',
            '<foreignobject',
            'javascript:',
            'data:text/html',
        ];
        foreach ($dangerous as $needle) {
            if (str_contains($lower, $needle)) {
                throw ValidationException::withMessages([
                    'file' => __('validation.theme_image.svg_unsafe'),
                ]);
            }
        }
        // Event handlers inline (onclick, onload, onerror, ...).
        if (preg_match('/\son[a-z]+\s*=/i', $content) === 1) {
            throw ValidationException::withMessages([
                'file' => __('validation.theme_image.svg_unsafe'),
            ]);
        }
    }

    /**
     * Descarga una imagen de URL remota con validación anti-SSRF.
     *
     * @throws ValidationException
     */
    public function ingestFromUrl(string $themeId, string $url): UploadedMediaDto
    {
        // Parsear y validar URL.
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.url_invalid')]);
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.url_scheme')]);
        }

        $host = $parsed['host'];

        // Resolver TODAS las IPs (A + AAAA) del host y validar anti-SSRF en cada
        // una. Un host con varias A o con AAAA puede esconder un loopback/RFC1918
        // en alguna de ellas, y gethostbyname() solo devuelve la primera A.
        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.url_unreachable')]);
        }
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw ValidationException::withMessages(['url' => __('validation.theme_image.private_network')]);
            }
        }

        // Descargar con timeout corto y SIN seguir redirects: un 30x podría apuntar
        // a un host privado tras pasar la validación inicial.
        try {
            $response = Http::timeout(5)->withoutRedirecting()->get($url);
            // Una redirección no seguida ya no es una respuesta válida para nosotros.
            if ($response->status() >= 300 && $response->status() < 400) {
                throw ValidationException::withMessages(['url' => __('validation.theme_image.download_failed')]);
            }
            $response->throw();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.download_failed')]);
        }

        // Validar content-type.
        $contentType = $response->header('content-type');
        if (! $this->isValidImageContentType($contentType)) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.not_image')]);
        }

        // Validar tamaño (≤10MB).
        $body = $response->body();
        if (strlen($body) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.too_large')]);
        }

        // Sanea contenido SVG (las imágenes raster pasan sin tocar).
        $this->validateContent($body);

        // Almacenar.
        $uuid = (string) Str::uuid();
        $path = "themes/{$themeId}/{$uuid}";
        Storage::disk('media')->put($path, $body);

        return new UploadedMediaDto(src: $path, uuid: $uuid);
    }

    /**
     * Valida que el content-type sea una imagen permitida.
     */
    private function isValidImageContentType(?string $contentType): bool
    {
        if ($contentType === null) {
            return false;
        }

        // Extrae el tipo base (ignora parámetros como charset).
        $baseType = explode(';', $contentType)[0];
        $baseType = strtolower(trim($baseType));

        $allowed = [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/webp',
            'image/svg+xml',
        ];

        return in_array($baseType, $allowed, true);
    }

    /**
     * Resuelve todas las IPs (A + AAAA) de un host. Devuelve [] si no resuelve.
     *
     * @return list<string>
     */
    private function resolveHostIps(string $host): array
    {
        // Si el host ya es una IP literal (con o sin corchetes IPv6), úsala tal cual.
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (! empty($r['ip'])) {
                    $ips[] = $r['ip'];
                } elseif (! empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }
        if ($ips === []) {
            // Fallback IPv4 si dns_get_record no devolvió nada (red sin AAAA).
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Una IP es "pública" si:
     *  - es una IP válida,
     *  - no está en rangos privados / reservados (RFC1918, loopback, link-local,
     *    multicast, ULA fc00::/7, IPv6 link-local, ::1, ::, 0.0.0.0, etc.),
     *  - y no es uno de los catch-all peligrosos.
     */
    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $blocked = ['0.0.0.0', '::', '::1'];
        if (in_array($ip, $blocked, true)) {
            return false;
        }

        // IPv4-mapped IPv6 (::ffff:127.0.0.1) — extraer la parte v4 y revalidar.
        if (str_starts_with($ip, '::ffff:')) {
            $v4 = substr($ip, 7);

            return filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }
}
