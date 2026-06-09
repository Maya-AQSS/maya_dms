<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

interface ThemeImageServiceInterface
{
    /**
     * Sube un archivo de imagen al almacenamiento media y devuelve
     * el path interno y UUID.
     *
     * @return array{src: string, uuid: string}
     */
    public function upload(string $themeId, UploadedFile $file): array;

    /**
     * Descarga una imagen de una URL remota, valida anti-SSRF,
     * y la almacena como media. Devuelve path interno y UUID.
     *
     * @return array{src: string, uuid: string}
     *
     * @throws ValidationException
     */
    public function ingestFromUrl(string $themeId, string $url): array;
}
