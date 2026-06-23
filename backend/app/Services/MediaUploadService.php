<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Media\UploadedMediaDto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Base para servicios que suben imágenes al disco `media` bajo
 * `{scope}/{contextId}/{uuid}` y devuelven el path interno + uuid. Centraliza la
 * mecánica común de {@see CoverImageService} y {@see ThemeImageService}.
 *
 * Las subclases definen el prefijo de scope y, opcionalmente, una validación de
 * contenido adicional (hook `validateContent`) — p. ej. comprobar magic-bytes
 * para rechazar archivos que mienten sobre su extensión.
 */
abstract class MediaUploadService
{
    /** Prefijo del path en el disco media (p. ej. 'covers' | 'themes'). */
    abstract protected function scopePrefix(): string;

    public function upload(string $contextId, UploadedFile $file): UploadedMediaDto
    {
        $content = $file->getContent();
        $this->validateContent($content);
        // Saneado opcional: las subclases pueden devolver una versión limpia del
        // contenido (p. ej. SVG pasado por un allowlist sanitizer) que es lo que
        // se persiste — no el original.
        $content = $this->sanitizeContent($content);

        $uuid = (string) Str::uuid();
        $path = $this->scopePrefix().'/'.$contextId.'/'.$uuid;

        Storage::disk('media')->put($path, $content);

        return new UploadedMediaDto(src: $path, uuid: $uuid);
    }

    /**
     * Hook de validación de contenido. Por defecto no hace nada (la validación
     * de tipo/tamaño la cubre el FormRequest); las subclases pueden reforzarla.
     */
    protected function validateContent(string $content): void {}

    /**
     * Hook de saneado de contenido: devuelve la versión a persistir. Por defecto
     * el contenido tal cual; las subclases pueden limpiarlo (allowlist).
     */
    protected function sanitizeContent(string $content): string
    {
        return $content;
    }
}
