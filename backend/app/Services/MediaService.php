<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Media\MediaDto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    private const DISK = 'media';
    private const ALLOWED_CONTEXT_TYPES = ['block', 'template', 'document', 'theme'];

    /**
     * Store an uploaded media file and return a DTO with the signed URL.
     *
     * @param UploadedFile $file
     * @param string|null $contextType One of: block, template, document, theme
     * @param string|null $contextId UUID of the context
     * @return MediaDto
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
     * @param string $uuid
     * @param string|null $contextType
     * @param string|null $contextId
     * @param string $token
     * @return string File content
     * @throws \Exception If token is invalid or file not found
     */
    public function retrieve(string $uuid, ?string $contextType = null, ?string $contextId = null, ?string $token = null): string
    {
        $this->validateContextParams($contextType, $contextId);

        $path = $this->buildPath($contextType, $contextId, $uuid);

        if ($token !== null && !hash_equals($this->makeToken($path), $token)) {
            throw new \Exception('Token de media inválido.');
        }

        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($path)) {
            throw new \Exception('Imagen no encontrada.');
        }

        return $disk->get($path);
    }

    /**
     * Detect MIME type from file content.
     *
     * @param string $content
     * @return string
     */
    public function detectMimeType(string $content): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';
    }

    /**
     * Build the storage path for a media file.
     *
     * @param string|null $contextType
     * @param string|null $contextId
     * @param string $uuid
     * @return string
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
     *
     * @param string $uuid
     * @param string|null $contextType
     * @param string|null $contextId
     * @param string $token
     * @return string
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
     *
     * @param string $path
     * @return string
     */
    private function makeToken(string $path): string
    {
        return hash_hmac('sha256', $path, (string) config('app.key'));
    }

    /**
     * Validate context type and ID parameters.
     *
     * @param string|null $contextType
     * @param string|null $contextId
     * @throws \Exception If parameters are invalid
     */
    private function validateContextParams(?string $contextType, ?string $contextId): void
    {
        if ($contextType !== '' && $contextType !== null) {
            if (!in_array($contextType, self::ALLOWED_CONTEXT_TYPES, true) || !Str::isUuid($contextId)) {
                throw new \Exception('Token de media inválido.');
            }
        }
    }
}
