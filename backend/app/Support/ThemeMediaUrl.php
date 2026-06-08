<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Helper para construir URLs firmadas HMAC para imágenes de theme.
 * Reutilizable en controladores, recursos y servicios.
 */
final class ThemeMediaUrl
{
    /**
     * Convierte un path interno (themes/{themeId}/{uuid}) a una URL
     * firmada con HMAC que puede ser leída por <img> y WeasyPrint sin JWT.
     *
     * Retorna null para paths vacíos o inválidos.
     */
    public static function build(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $uuid = basename($path);
        if (! Str::isUuid($uuid)) {
            return null; // Legacy path — debe re-subirse.
        }

        $parts = explode('/', $path);
        $token = hash_hmac('sha256', $path, (string) config('app.key'));
        $base = route('api.v1.media.show', ['uuid' => $uuid]);

        if (count($parts) >= 3) {
            $ct = rtrim($parts[0], 's'); // 'themes' → 'theme'
            $ci = $parts[1];
            return "{$base}?ct={$ct}&ci={$ci}&token={$token}";
        }

        return "{$base}?token={$token}";
    }
}
