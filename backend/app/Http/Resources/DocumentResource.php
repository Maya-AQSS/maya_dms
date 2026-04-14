<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Convierte el documento en un array para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'template_version_id' => $this->template_version_id,
            'title' => $this->title,
            'organization_id' => $this->organization_id,
            'study_id' => $this->study_id,
            'module_id' => $this->module_id,
            'created_by' => $this->created_by,
            'owner_id' => $this->owner_id,
            'status' => $this->status,
            'current_version' => $this->current_version,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
