<?php

namespace App\Http\Resources;

use App\Support\ApiEmbeddedTeamResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * Convierte la plantilla en un array para la respuesta JSON.
     * 
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'description'        => $this->description,
            'visibility_level'   => $this->visibility_level->value,
            'delivery_deadline'  => $this->delivery_deadline?->toIso8601String(),
            'study_type_id'      => $this->study_type_id,
            'study_id'           => $this->study_id,
            'module_id'          => $this->module_id,
            'team_id'            => $this->team_id,
            'team'               => $this->resource->getAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY),
            'created_by'         => $this->created_by,
            'status'             => $this->status,
            'version'            => $this->version,
            'review_stages'      => $this->review_stages,
            'review_mode'        => $this->review_mode,
            'reviewers'           => $this->whenLoaded('reviewers', fn () => $this->reviewers
                ->sortBy('stage')
                ->values()
                ->map(fn ($r) => ['user_id' => $r->user_id, 'stage' => $r->stage])
                ->all()),
            'document_reviewers' => $this->whenLoaded('documentReviewers', fn () => $this->documentReviewers
                ->map(fn ($v) => $v->user_id)
                ->values()
                ->all()),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
