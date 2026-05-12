<?php

namespace App\Http\Resources;

use App\Models\Process;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Process $resource
 */
class ProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->resource->id,
            'name'        => $this->resource->name,
            'description' => $this->resource->description,
            'created_at'  => optional($this->resource->created_at)->toIso8601String(),
            'updated_at'  => optional($this->resource->updated_at)->toIso8601String(),
        ];
    }
}
