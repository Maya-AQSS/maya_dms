<?php

namespace App\Http\Resources;

use App\Support\ApiEmbeddedTeamResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
            'process_id'         => $this->process_id,
            'team_id'            => $this->team_id,
            'team'               => $this->resource->getAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY),
            'created_by'         => $this->created_by,
            'author_name'        => $this->resource->author_name
                ?? ($this->resource->relationLoaded('creator') ? $this->resource->creator?->name : null),
            'status'             => $this->status,
            'version'            => $this->version,
            'review_stages'      => $this->review_stages,
            'review_mode'        => $this->review_mode,
            'reviewers'           => $this->whenLoaded('reviewers', fn () => $this->reviewers
                ->sortBy('stage')
                ->values()
                ->map(fn ($r) => [
                    'user_id' => $r->user_id,
                    'user_name' => optional($r->user)->name,
                    'stage' => $r->stage,
                    'status' => $r->status
                ])
                ->all()),
            'document_reviewers' => $this->whenLoaded('documentReviewers', fn () => $this->documentReviewers
                ->map(fn ($v) => $v->user_id)
                ->values()
                ->all()),
            'document_reviewer_users' => $this->whenLoaded('documentReviewers', fn () => $this->documentReviewers
                ->map(fn ($v) => [
                    'user_id' => $v->user_id,
                    'user_name' => optional($v->user)->name,
                ])
                ->values()
                ->all()),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
            'has_review_comments' => (bool) ($this->resource->has_review_comments ?? false),
            'latest_published_version_id' => $this->resource->getAttribute('latest_published_version_id'),
            'latest_published_version_number' => $this->resource->getAttribute('latest_published_version_number'),
            'can_clone' => (bool) ($this->resource->getAttribute('can_clone') ?? false),
            'working_version_id' => $this->head_entity_version_id,
            'review_history' => $this->whenLoaded('headVersion', fn () => $this->headVersion?->change_set),
            'latest_published_name' => $this->resource->getAttribute('latest_published_name'),
            'blocks_at_previous_submission' => $this->whenLoaded('headVersion', function () {
                $data = $this->headVersion?->snapshot_data;
                return is_array($data) && isset($data['blocks_at_previous_submission'])
                    ? $data['blocks_at_previous_submission']
                    : null;
            }),
        ];
    }

    private function formatOptionalIso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
