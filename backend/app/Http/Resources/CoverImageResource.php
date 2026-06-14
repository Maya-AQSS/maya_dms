<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Media\UploadedMediaDto;
use App\Services\CoverImageService;
use App\Support\ThemeMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Respuesta de subida de imagen de portada (cover).
 *
 * Mapea el {@see UploadedMediaDto} devuelto por {@see CoverImageService::upload}
 * al `src` interno (para el render PDF/preview) más la `url` firmada
 * (para mostrar la imagen en el editor de diseño).
 *
 * @property-read UploadedMediaDto $resource
 */
class CoverImageResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'src' => $this->resource->src,
            'url' => ThemeMediaUrl::build($this->resource->src),
        ];
    }
}
