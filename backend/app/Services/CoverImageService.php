<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ThemeMediaUrl;
use Illuminate\Validation\ValidationException;

/**
 * Sube imágenes para bloques de portada (cover) al disco `media` bajo
 * `covers/{templateId}/{uuid}`. El `src` devuelto es el path relativo que
 * {@see MediaAssetResolver} resuelve por `file://` (PDF) o base64 (preview), y
 * que {@see ThemeMediaUrl::build()} firma para servir en el editor.
 *
 * No admite ingesta desde URL (a diferencia del tema): la portada sólo sube
 * archivos, evitando la superficie SSRF. Además valida magic-bytes para rechazar
 * archivos que mienten sobre su extensión (p. ej. un SVG renombrado a .png).
 */
class CoverImageService extends MediaUploadService
{
    private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp'];

    protected function scopePrefix(): string
    {
        return 'covers';
    }

    protected function validateContent(string $content): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: '';
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw ValidationException::withMessages([
                'file' => 'El archivo no es una imagen válida (PNG, JPG o WebP).',
            ]);
        }
    }
}
