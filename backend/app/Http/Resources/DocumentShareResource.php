<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de respuesta para compartición de documento.
 */
class DocumentShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource['user_id'],
            'permission' => $this->resource['permission'],
            'granted_by' => $this->resource['granted_by'],
        ];
    }
}
