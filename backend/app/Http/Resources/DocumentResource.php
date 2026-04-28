<?php

namespace App\Http\Resources;

use App\Models\TemplateVersion;
use App\Support\ApiEmbeddedTeamResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Convierte el documento en un array para la respuesta JSON.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'template_version_id' => $this->template_version_id,
            'template_version_number' => $this->resolveTemplateVersionNumber(),
            'team' => $this->resource->getAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY),
            'title' => $this->title,
            'study_type_id' => $this->study_type_id,
            'study_id' => $this->study_id,
            'module_id' => $this->module_id,
            'delivery_deadline' => $this->delivery_deadline?->toIso8601String(),
            'created_by' => $this->created_by,
            'owner_id' => $this->owner_id,
            'owner_name' => $this->resource->owner_name ?? null,
            'status' => $this->status,
            'current_version' => $this->current_version,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_shared_with_me' => (bool) ($this->resource->getAttribute('is_shared_with_me') ?? false),
            'share_permission' => $this->resource->getAttribute('viewer_share_permission'),
        ];
    }

    private function resolveTemplateVersionNumber(): ?int
    {
        if ($this->template_version_id === null) {
            return null;
        }

        if ($this->relationLoaded('templateVersion') && $this->templateVersion !== null) {
            return (int) $this->templateVersion->version_number;
        }

        $n = TemplateVersion::query()->whereKey($this->template_version_id)->value('version_number');

        return $n !== null ? (int) $n : null;
    }
}
