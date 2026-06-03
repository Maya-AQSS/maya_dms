<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Media\MediaDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property MediaDto $resource
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var MediaDto $dto */
        $dto = $this->resource;

        return [
            'url' => $dto->url,
        ];
    }
}
