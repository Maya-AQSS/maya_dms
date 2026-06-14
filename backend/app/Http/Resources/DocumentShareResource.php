<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\DocumentShareResultDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de respuesta para compartición de documento.
 *
 * @property-read DocumentShareResultDto $resource
 */
class DocumentShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource->userId,
            'permission' => $this->resource->permission,
            'granted_by' => $this->resource->grantedBy,
        ];
    }
}
