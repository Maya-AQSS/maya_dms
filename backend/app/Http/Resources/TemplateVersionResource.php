<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVersionResource extends JsonResource
{
    /**
     * Convierte la versión de plantilla en un array para la respuesta JSON (con el JSONB de bloques).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'version_number' => $this->version_number,
            'blocks_snapshot' => $this->blocks_snapshot,
            'changelog' => $this->changelog,
            'published_by' => $this->published_by,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
