<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Processes\ProcessDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El recurso recibe un `ProcessDto` tipado emitido por ProcessService,
 * no modelos Eloquent ni arrays sueltos.
 *
 * @property-read ProcessDto $resource
 */
class ProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'alias' => $this->resource->alias,
            'icon' => $this->resource->icon,
            'color' => $this->resource->color,
            'description' => $this->resource->description,
            'process_parent_id' => $this->resource->processParentId,
        ];
    }
}
