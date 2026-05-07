<?php

namespace App\Http\Resources;

use App\Services\TemplateVersionBlockLayerResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
        $snapshotData = is_array($this->snapshot_data) ? $this->snapshot_data : [];
        $templateSnapshot = isset($snapshotData['template']) && is_array($snapshotData['template'])
            ? $snapshotData['template']
            : null;
        $authorId = isset($templateSnapshot['created_by']) && is_string($templateSnapshot['created_by']) && $templateSnapshot['created_by'] !== ''
            ? $templateSnapshot['created_by']
            : (is_string($this->created_by) && $this->created_by !== '' ? $this->created_by : null);
        $publishedBy = is_string($this->published_by) && $this->published_by !== '' ? $this->published_by : null;

        $publishedAt = $this->published_at ?? null;

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
            'published_at' => $publishedAt?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveUserNameById(string $userId): ?string
    {
        static $cache = [];
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }
        $name = DB::table('users')->where('id', $userId)->value('name');
        $cache[$userId] = is_string($name) && trim($name) !== '' ? trim($name) : null;
        return $cache[$userId];
    }
}
