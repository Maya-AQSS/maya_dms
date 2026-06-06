<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\DocumentMigrationPayloadDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el payload de migración de versión de plantilla.
 *
 * @property DocumentMigrationPayloadDto $resource
 */
class DocumentMigrationPayloadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
