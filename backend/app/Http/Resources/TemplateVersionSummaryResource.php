<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Historial de versiones publicadas (sin el JSONB de bloques).
 */
class TemplateVersionSummaryResource extends JsonResource
{
    /**
     * Convierte la versión de plantilla en un array para la respuesta JSON (sin el JSONB de bloques).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $templateId = $this->template_id ?? $this->versionable_id;
        $publishedAt = $this->published_at ?? null;

        return [
            'id' => $this->id,
            'template_id' => $templateId,
            'version_number' => $this->version_number,
            'published_at' => $publishedAt?->toIso8601String(),
            'published_by' => $this->published_by,
            'changelog' => $this->changelog,
        ];
    }
}
