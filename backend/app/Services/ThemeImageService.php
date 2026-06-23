<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Media\UploadedMediaDto;
use App\Services\Contracts\ThemeImageServiceInterface;
use App\Support\Svg\ThemeSvgAllowedAttributes;
use App\Support\Svg\ThemeSvgAllowedTags;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

class ThemeImageService extends MediaUploadService implements ThemeImageServiceInterface
{
    protected function scopePrefix(): string
    {
        return 'themes';
    }

    // upload() se hereda de MediaUploadService (themes admite SVG, validado por
    // el FormRequest + content-type en la ingesta remota).

    /**
     * DMS-B10b: los SVG se sanean con un allowlist (enshrined/svg-sanitize) en
     * {@see sanitizeContent} y se persiste la versión limpia — en lugar del
     * blocklist anterior, que era frágil ante vectores no enumerados. Aquí solo
     * detectamos que el XML del SVG sea parseable (sanitize() falla en XML roto).
     * PNG/JPEG/WEBP pasan sin inspección (magic bytes ya validados por el FormRequest).
     * El MediaController además sirve cualquier SVG como attachment con CSP estricta.
     */
    protected function looksLikeSvg(string $content): bool
    {
        $head = ltrim(substr($content, 0, 512));

        return $head !== ''
            && (str_starts_with($head, '<?xml')
                || stripos($head, '<svg') !== false);
    }

    /**
     * Saneado allowlist del SVG: elimina scripts, handlers de eventos,
     * referencias remotas y cualquier elemento/atributo fuera de la allowlist.
     * Devuelve el SVG limpio (lo que se persiste). Rechaza solo XML no parseable.
     *
     * @throws ValidationException
     */
    protected function sanitizeContent(string $content): string
    {
        if (! $this->looksLikeSvg($content)) {
            return $content;
        }

        $sanitizer = new Sanitizer;
        // Defensa anti-SSRF/exfiltración: descarta url(...) remotos y, vía la
        // allowlist propia, elimina <image>/<a>/<use>/<foreignObject> y los
        // atributos href/xlink:href (un logo de tema no los necesita).
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->setAllowedTags(new ThemeSvgAllowedTags);
        $sanitizer->setAllowedAttrs(new ThemeSvgAllowedAttributes);

        $clean = $sanitizer->sanitize($content);
        if ($clean === false || trim($clean) === '') {
            throw ValidationException::withMessages([
                'file' => __('validation.theme_image.svg_unsafe'),
            ]);
        }

        return $clean;
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

        // userinfo en URL: parse_url y la lib HTTP/curl pueden disentir sobre el
        // host real (`http://10.0.0.1@evil.com/`). Rechazar la forma directamente.
        if (! empty($parsed['user']) || ! empty($parsed['pass'])) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.url_invalid')]);
        }

        $host = $parsed['host'];

        // Hosts en forma de literal numérico (decimal, octal o hex) son aceptados
        // por curl como IPs (`http://2130706433/` → 127.0.0.1) y suelen saltarse
        // la validación de filter_var. Rechazar tajante.
        if (preg_match('/^(0x[0-9a-f]+|0[0-7]+|[0-9]+)$/i', $host) === 1) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.url_invalid')]);
        }
        // Host con caracteres URL-encoded o trailing dot también merecen rechazo.
        if (str_contains($host, '%')) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.url_invalid')]);
        }

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

        // Anti-DNS-rebinding: pinear la conexión a la IP ya validada vía
        // CURLOPT_RESOLVE. Si Http no usa curl (entornos de test con fake), la
        // opción se ignora silenciosamente — el fallback es la validación previa.
        $pinnedIp = $ips[0];
        // Si la IP es IPv6, envolverla en corchetes para la cabecera Host implícita.
        $resolveEntries = [
            sprintf('%s:443:%s', $host, $pinnedIp),
            sprintf('%s:80:%s', $host, $pinnedIp),
        ];

        // Descargar con timeout corto y SIN seguir redirects: un 30x podría apuntar
        // a un host privado tras pasar la validación inicial.
        try {
            $response = Http::timeout(5)
                ->withoutRedirecting()
                ->withOptions([
                    'curl' => [
                        CURLOPT_RESOLVE => $resolveEntries,
                    ],
                ])
                ->get($url);
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

        // Sanea contenido SVG con el allowlist y persiste la versión limpia (las
        // imágenes raster pasan sin tocar). DMS-B10b: cubre también la ingesta remota.
        $body = $this->sanitizeContent($body);

        // Almacenar. Con `throw => true` en el disco, un fallo de IO en NFS sale
        // como FilesystemException; lo traducimos a ValidationException para no
        // filtrar el path interno en la respuesta de error.
        $uuid = (string) Str::uuid();
        $path = "themes/{$themeId}/{$uuid}";
        try {
            Storage::disk('media')->put($path, $body);
        } catch (FilesystemException $e) {
            throw ValidationException::withMessages(['url' => __('validation.theme_image.storage_failed')]);
        }

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
     *  - no está en CGNAT 100.64.0.0/10 (RFC6598 — usable como pod CIDR en K3s),
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
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }

            return $this->isPublicIpv4($v4);
        }

        // IPv4: aplicar también el corte CGNAT (RFC6598 100.64.0.0/10) que
        // FILTER_FLAG_NO_PRIV_RANGE no cubre.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $this->isPublicIpv4($ip);
        }

        return true;
    }

    /**
     * Comprobaciones extra IPv4 que filter_var no cubre (CGNAT RFC6598).
     */
    private function isPublicIpv4(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        // 100.64.0.0/10 — Shared Address Space (CGNAT).
        if (($long & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
            return false;
        }

        return true;
    }
}
