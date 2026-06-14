<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Templates\StoreCoverImageRequest;
use App\Http\Resources\CoverImageResource;
use App\Services\CoverImageService;
use Illuminate\Http\JsonResponse;

/**
 * Subida de imágenes para bloques de portada (cover) de una plantilla.
 * Devuelve el `src` interno (para el render PDF/preview) + `url` firmada
 * (para mostrar la imagen en el editor de diseño).
 */
class CoverImageController
{
    public function __construct(
        private readonly CoverImageService $service,
    ) {}

    public function store(StoreCoverImageRequest $request, string $template): JsonResponse
    {
        $result = $this->service->upload($template, $request->file('file'));

        return (new CoverImageResource($result))->response()->setStatusCode(201);
    }
}
