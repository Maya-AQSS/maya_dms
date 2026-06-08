<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\ThemeImageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ThemeImageService implements ThemeImageServiceInterface
{
    /**
     * Sube un archivo de imagen al almacenamiento media.
     *
     * @return array{src: string, uuid: string}
     */
    public function upload(string $themeId, UploadedFile $file): array
    {
        $uuid = (string) Str::uuid();
        $path = "themes/{$themeId}/{$uuid}";

        Storage::disk('media')->put($path, $file->getContent());

        return [
            'src' => $path,
            'uuid' => $uuid,
        ];
    }

    /**
     * Descarga una imagen de URL remota con validación anti-SSRF.
     *
     * @throws ValidationException
     * @return array{src: string, uuid: string}
     */
    public function ingestFromUrl(string $themeId, string $url): array
    {
        // Parsear y validar URL.
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw ValidationException::withMessages(['url' => 'La URL no es válida.']);
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages(['url' => 'Solo se permiten URLs http/https.']);
        }

        $host = $parsed['host'];

        // Resolver host a IP y validar anti-SSRF.
        $ip = @gethostbyname($host);
        if ($ip === $host || $ip === false) {
            // No se resolvió o devolvió el hostname sin cambios.
            throw ValidationException::withMessages(['url' => 'No se puede acceder a esta URL.']);
        }

        // Rechazar IPs privadas/loopback/reservadas.
        if (@filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw ValidationException::withMessages(['url' => 'No se pueden descargar recursos de redes privadas.']);
        }

        // Rechazar explícitamente ::1, 127.0.0.0/8, etc.
        if (in_array($ip, ['::1', '0.0.0.0'], true)) {
            throw ValidationException::withMessages(['url' => 'No se pueden descargar recursos de redes privadas.']);
        }

        // Descargar con timeout corto.
        try {
            $response = Http::timeout(5)->get($url);
            $response->throw();
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['url' => 'No se pudo descargar la imagen.']);
        }

        // Validar content-type.
        $contentType = $response->header('content-type');
        if (! $this->isValidImageContentType($contentType)) {
            throw ValidationException::withMessages(['url' => 'El archivo no es una imagen válida.']);
        }

        // Validar tamaño (≤10MB).
        $body = $response->body();
        if (strlen($body) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['url' => 'La imagen es demasiado grande (máximo 10MB).']);
        }

        // Almacenar.
        $uuid = (string) Str::uuid();
        $path = "themes/{$themeId}/{$uuid}";
        Storage::disk('media')->put($path, $body);

        return [
            'src' => $path,
            'uuid' => $uuid,
        ];
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
}
