<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'template_id'     => $this->template_id,
            'title'           => $this->title,
            'default_content' => $this->default_content,
            'description'     => $this->description,
            'block_state'     => $this->block_state,
            'sort_order'      => $this->sort_order,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
