<?php

namespace App\Http\Resources;

use App\DTOs\TemplateBlocks\TemplateBlockDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dto = $this->resource instanceof TemplateBlockDto
            ? $this->resource
            : TemplateBlockDto::fromModel($this->resource);

        return [
            'id'              => $dto->id,
            'template_id'     => $dto->templateId,
            'title'           => $dto->title,
            'default_content' => $dto->defaultContent,
            'description'     => $dto->description,
            'block_state'     => $dto->blockState,
            'sort_order'      => $dto->sortOrder,
            'created_at'      => $dto->createdAt,
            'updated_at'      => $dto->updatedAt,
        ];
    }
}
