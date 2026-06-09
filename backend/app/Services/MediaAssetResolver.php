<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resuelve un path interno de media (`covers/{uuid}/{uuid}` o
 * `themes/{uuid}/{uuid}`) a una URL embebible en el HTML de render:
 *   - preview (iframe blob): `data:` URI base64 (el iframe no puede leer file://).
 *   - PDF (WeasyPrint): `file://` directo del filesystem del contenedor.
 *
 * Fuente única de verdad para CoverRenderService y el Blade `documents.render`
 * (antes duplicada en ambos). Valida anti path-traversal: sólo acepta los dos
 * prefijos conocidos con nombre de archivo UUID; rechaza `..` y rutas arbitrarias
 * aunque el path provenga de datos manipulados.
 */
final class MediaAssetResolver
{
    private const SAFE_PATH = '#^(covers|themes)/[0-9a-f-]{36}/[0-9a-f-]{36}$#i';

    public static function resolve(?string $relativePath, bool $previewMode): ?string
    {
        if (! $relativePath) {
            return null;
        }
        $relativePath = ltrim($relativePath, '/');
        if (preg_match(self::SAFE_PATH, $relativePath) !== 1) {
            return null;
        }
        $absolute = storage_path('app/media/'.$relativePath);
        if (! is_file($absolute)) {
            return null;
        }
        if ($previewMode) {
            $mime = @mime_content_type($absolute) ?: 'image/png';
            $contents = @file_get_contents($absolute);
            if ($contents === false) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        return 'file://'.$absolute;
    }
}
