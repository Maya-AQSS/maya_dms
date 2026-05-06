<?php

namespace App\Http\Resources;

use App\Models\TemplateVersion;
use App\Services\TemplateVersionBlockLayerResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVersionResource extends JsonResource
{
    /**
     * Convierte la versión de plantilla en un array para la respuesta JSON (con el JSONB de bloques).
     *
     * Para filas {@see TemplateVersion}, `blocks_snapshot` se reconstruye con capas incrementales
     * cuando existen; si no, el resolver usa el JSON guardado (paridad con documentos).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $templateId = $this->template_id ?? $this->versionable_id;
        $snapshotData = is_array($this->snapshot_data ?? null) ? $this->snapshot_data : [];

        if ($this->resource instanceof TemplateVersion) {
            $blocksSnapshot = app(TemplateVersionBlockLayerResolver::class)
                ->resolveBlocksSnapshot((string) $this->resource->getKey());
        } else {
            $blocksSnapshot = $this->blocks_snapshot ?? ($snapshotData['blocks'] ?? []);
        }

        $publishedAt = $this->published_at ?? null;

        return [
            'id' => $this->id,
            'template_id' => $templateId,
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
