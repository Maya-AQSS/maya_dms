<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Versioning\EntityVersionDto;
use App\Http\Resources\Concerns\ResolvesUserNames;
use App\Services\TemplateVersionBlockLayerResolver;
use App\Support\TemplateVersionSnapshotParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property EntityVersionDto $resource
 */
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
        /** @var EntityVersionDto $dto */
        $dto = $this->resource;

        $blocksSnapshot = app(TemplateVersionBlockLayerResolver::class)
            ->resolveBlocksSnapshot($dto->id);
        $snapshotData = $dto->snapshotData ?? [];
        $templateSnapshot = isset($snapshotData['template']) && is_array($snapshotData['template'])
            ? $snapshotData['template']
            : null;

        $authorId = TemplateVersionSnapshotParser::authorId($snapshotData);
        if ($authorId === null && is_string($dto->createdBy) && $dto->createdBy !== '') {
            $authorId = $dto->createdBy;
        }

        $publishedBy = $dto->publishedBy !== null && $dto->publishedBy !== '' ? $dto->publishedBy : null;

        $reviewerNames = [];
        foreach (TemplateVersionSnapshotParser::reviewerIds($snapshotData) as $uid) {
            $name = $this->resolveUserNameById($uid);
            if ($name !== null) {
                $reviewerNames[] = $name;
            }
        }

        return [
            'id' => $dto->id,
            'template_id' => $dto->versionableId,
            'version_number' => $dto->versionNumber,
            'template_snapshot' => $templateSnapshot,
            'blocks_snapshot' => $blocksSnapshot,
            'changelog' => $dto->changelog,
            'published_by' => $publishedBy,
            'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
            'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
            'reviewer_names' => $reviewerNames,
            'published_at' => $dto->publishedAt,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}
