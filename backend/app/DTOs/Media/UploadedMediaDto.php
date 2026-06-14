<?php

declare(strict_types=1);

namespace App\DTOs\Media;

use App\Services\MediaUploadService;
use App\Services\ThemeImageService;

/**
 * Resultado de almacenar un asset en el disco `media`.
 *
 * Fija el contrato de salida de {@see MediaUploadService::upload}
 * y {@see ThemeImageService::ingestFromUrl}: el path interno
 * (`src`) y el UUID generado.
 */
final readonly class UploadedMediaDto
{
    public function __construct(
        public string $src,
        public string $uuid,
    ) {}

    /**
     * @return array{src: string, uuid: string}
     */
    public function toArray(): array
    {
        return [
            'src' => $this->src,
            'uuid' => $this->uuid,
        ];
    }
}
