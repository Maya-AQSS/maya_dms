<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Themes\ThemeDto;
use Illuminate\Http\UploadedFile;

interface ThemeAssetServiceInterface
{
    /**
     * Sube un asset (logo/background/watermark) al disco `themes` bajo
     * `{theme_id}/{kind}-{uuid}.{ext}` y actualiza la columna JSONB
     * correspondiente en el Theme. Devuelve el Theme actualizado.
     */
    public function upload(string $themeId, string $assetKey, UploadedFile $file): ThemeDto;
}
