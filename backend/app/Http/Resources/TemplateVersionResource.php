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
        $templateId = $this->template_id ?? $this->versionable_id;
        $snapshotData = is_array($this->snapshot_data ?? null) ? $this->snapshot_data : [];
        $blocksSnapshot = $this->blocks_snapshot ?? ($snapshotData['blocks'] ?? []);
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
