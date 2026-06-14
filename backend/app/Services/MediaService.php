<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Media\MediaDto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaService
{
    private const DISK = 'media';

    private const ALLOWED_CONTEXT_TYPES = ['block', 'template', 'document', 'theme', 'cover'];

    /**
     * Store an uploaded media file and return a DTO with the signed URL.
     *
     * @param  string|null  $contextType  One of: block, template, document, theme
     * @param  string|null  $contextId  UUID of the context
     */
    public function store(UploadedFile $file, ?string $contextType = null, ?string $contextId = null): MediaDto
    {
        $uuid = Str::uuid()->toString();
        $path = $this->buildPath($contextType, $contextId, $uuid);

        Storage::disk(self::DISK)->put($path, $file->getContent());

        $token = $this->makeToken($path);
        $url = $this->buildUrl($uuid, $contextType, $contextId, $token);

        return new MediaDto($url);
    }

    /**
     * Retrieve media file content by UUID and validate token.
     *
     * @return string File content
     *
     * @throws AccessDeniedHttpException If the HMAC token or context is invalid (HTTP 403)
     * @throws NotFoundHttpException If the file does not exist (HTTP 404)
     */
    public function retrieve(string $uuid, ?string $contextType = null, ?string $contextId = null, ?string $token = null): string
    {
        $this->validateContextParams($contextType, $contextId);

        $path = $this->buildPath($contextType, $contextId, $uuid);

        // El token HMAC es la única autorización de esta ruta pública (servida sin
        // JWT para que <img src> funcione). Por eso es obligatorio: un token
        // ausente o vacío se rechaza siempre — no hay "modo sin token".
        if ($token === null || $token === '' || ! hash_equals($this->makeToken($path), $token)) {
            throw new AccessDeniedHttpException(__('media.invalid_token'));
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException(__('media.image_not_found'));
        }

        return $disk->get($path);
    }

    /**
     * Detect MIME type from file content.
     */
    public function detectMimeType(string $content): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';
    }

    /**
     * Build the storage path for a media file.
     */
    private function buildPath(?string $contextType, ?string $contextId, string $uuid): string
    {
        if ($contextType && $contextId) {
            return "{$contextType}s/{$contextId}/{$uuid}";
        }

        return "orphan/{$uuid}";
    }

    /**
     * Build the signed URL for a media file.
     */
    private function buildUrl(string $uuid, ?string $contextType, ?string $contextId, string $token): string
    {
        $base = route('api.v1.media.show', ['uuid' => $uuid]);

        if ($contextType && $contextId) {
            return "{$base}?ct={$contextType}&ci={$contextId}&token={$token}";
        }

        return "{$base}?token={$token}";
    }

    /**
     * Generate HMAC token for a path.
     */
    private function makeToken(string $path): string
    {
        return hash_hmac('sha256', $path, (string) config('app.key'));
    }

    /**
     * Validate context type and ID parameters.
     *
     * @throws AccessDeniedHttpException If parameters are invalid (HTTP 403)
     */
    private function validateContextParams(?string $contextType, ?string $contextId): void
    {
        if ($contextType !== '' && $contextType !== null) {
            if (! in_array($contextType, self::ALLOWED_CONTEXT_TYPES, true) || ! Str::isUuid($contextId)) {
                throw new AccessDeniedHttpException(__('media.invalid_token'));
            }
        }
    }
}
