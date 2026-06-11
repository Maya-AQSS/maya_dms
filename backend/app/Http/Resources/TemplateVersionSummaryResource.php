<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesUserNames;
use App\Support\TemplateVersionSnapshotParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Historial de versiones publicadas (sin el JSONB de bloques).
 */
class TemplateVersionSummaryResource extends JsonResource
{
    use ResolvesUserNames;

    /**
     * Convierte la versión de plantilla en un array para la respuesta JSON (sin el JSONB de bloques).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $templateId = $this->template_id ?? $this->versionable_id;
        $publishedAt = $this->published_at ?? null;
        $publishedBy = is_string($this->published_by) && $this->published_by !== '' ? $this->published_by : null;

        $authorId = TemplateVersionSnapshotParser::authorId($this->snapshot_data);
        if ($authorId === null && is_string($this->created_by) && $this->created_by !== '') {
            $authorId = $this->created_by;
        }

        $reviewerNames = [];
        foreach (TemplateVersionSnapshotParser::reviewerIds($this->snapshot_data) as $uid) {
            $name = $this->resolveUserNameById($uid);
            if ($name !== null) {
                $reviewerNames[] = $name;
            }
        }

        return [
            'id' => $this->id,
            'template_id' => $templateId,
            'version_number' => $this->version_number,
            'published_at' => $publishedAt?->toIso8601String(),
            'published_by' => $publishedBy,
            'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
            'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
            'reviewer_names' => $reviewerNames,
            'changelog' => $this->changelog,
        ];
    }
}
