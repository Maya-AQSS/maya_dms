<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesUserNames;
use App\Services\TemplateVersionBlockLayerResolver;
use App\Support\TemplateVersionSnapshotParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVersionResource extends JsonResource
{
    use ResolvesUserNames;

    /**
     * Detalle de publicación de plantilla (snapshot de bloques reconstruido con capas incrementales si existen).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $blocksSnapshot = app(TemplateVersionBlockLayerResolver::class)
            ->resolveBlocksSnapshot((string) $this->resource->getKey());
        $snapshotData = is_array($this->snapshot_data) ? $this->snapshot_data : [];
        $templateSnapshot = isset($snapshotData['template']) && is_array($snapshotData['template'])
            ? $snapshotData['template']
            : null;

        $authorId = TemplateVersionSnapshotParser::authorId($snapshotData);
        if ($authorId === null && is_string($this->created_by) && $this->created_by !== '') {
            $authorId = $this->created_by;
        }

        $publishedBy = is_string($this->published_by) && $this->published_by !== '' ? $this->published_by : null;
        $publishedAt = $this->published_at ?? null;

        $reviewerNames = [];
        foreach (TemplateVersionSnapshotParser::reviewerIds($snapshotData) as $uid) {
            $name = $this->resolveUserNameById($uid);
            if ($name !== null) {
                $reviewerNames[] = $name;
            }
        }

        return [
            'id' => $this->id,
            'template_id' => $this->versionable_id,
            'version_number' => $this->version_number,
            'template_snapshot' => $templateSnapshot,
            'blocks_snapshot' => $blocksSnapshot,
            'changelog' => $this->changelog,
            'published_by' => $publishedBy,
            'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
            'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
            'reviewer_names' => $reviewerNames,
            'published_at' => $publishedAt?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
