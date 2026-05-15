<?php

namespace App\Http\Resources;

use App\Services\DocumentTemplateVersionNumberResolver;
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
            'process_id' => $this->process_id,
            'template_id' => $this->template_id,
            'template_version_id' => $this->template_version_id,
            'template_version_number' => $this->resolveTemplateVersionNumber(),
            'team' => $this->resource->getAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY),
            'title' => $this->title,
            'study_type_id' => $this->study_type_id,
            'study_id' => $this->study_id,
            'module_id' => $this->module_id,
            'team_id' => $this->team_id,
            'delivery_deadline' => $this->delivery_deadline?->toIso8601String(),
            'created_by' => $this->created_by,
            'owner_id' => $this->owner_id,
            'owner_name' => $this->resource->owner_name
                ?? ($this->resource->relationLoaded('owner') ? $this->resource->owner?->name : null),
            'visibility_level' => $this->relationLoaded('template') && $this->template !== null
                ? $this->template->visibility_level->value
                : null,
            'status' => $this->status,
            'current_version' => $this->current_version,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_shared_with_me' => (bool) ($this->resource->getAttribute('is_shared_with_me') ?? false),
            'share_permission' => $this->resource->getAttribute('viewer_share_permission'),
            'can_clone' => (bool) ($this->resource->getAttribute('can_clone') ?? false),
            'working_version_id' => $this->head_entity_version_id,
            'review_history' => $this->whenLoaded('headVersion', fn () => $this->headVersion?->change_set),
            'latest_published_version_id' => $this->resource->getAttribute('latest_published_version_id'),
            'latest_published_version_number' => $this->resource->getAttribute('latest_published_version_number'),
            'latest_published_title' => $this->resource->getAttribute('latest_published_title'),
        ];
    }

    /**
     * Resuelve el número de versión de la plantilla anclada (p. ej. 1).
     */
    private function resolveTemplateVersionNumber(): ?int
    {
        $preloaded = $this->resource->getAttribute('template_version_number');
        if (is_numeric($preloaded)) {
            return (int) $preloaded;
        }

        if ($this->template_version_id === null) {
            return null;
        }

        if ($this->relationLoaded('templateVersion') && $this->templateVersion !== null) {
            return (int) $this->templateVersion->version_number;
        }

        return app(DocumentTemplateVersionNumberResolver::class)->resolve(
            $this->template_id !== null ? (string) $this->template_id : null,
            (string) $this->template_version_id,
        );
    }
}
