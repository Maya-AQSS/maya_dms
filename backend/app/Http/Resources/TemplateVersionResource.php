<?php

namespace App\Http\Resources;

use App\Services\TemplateVersionBlockLayerResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVersionResource extends JsonResource
{
    /**
     * Detalle de publicación de plantilla (snapshot de bloques reconstruido con capas incrementales si existen).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $blocksSnapshot = app(TemplateVersionBlockLayerResolver::class)
            ->resolveBlocksSnapshot((string) $this->resource->getKey());

        $publishedAt = $this->published_at ?? null;

        return [
            'id' => $this->id,
            'template_id' => $this->versionable_id,
            'version_number' => $this->version_number,
            'blocks_snapshot' => $blocksSnapshot,
            'changelog' => $this->changelog,
            'published_by' => $this->published_by,
            'published_at' => $publishedAt?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
