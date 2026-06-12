<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Versioning\EntityVersionDto;
use App\Http\Resources\Concerns\ResolvesUserNames;
use App\Support\TemplateVersionSnapshotParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Historial de versiones publicadas (sin el JSONB de bloques).
 *
 * @property EntityVersionDto $resource
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
        /** @var EntityVersionDto $dto */
        $dto = $this->resource;

        $publishedBy = $dto->publishedBy !== null && $dto->publishedBy !== '' ? $dto->publishedBy : null;

        $authorId = TemplateVersionSnapshotParser::authorId($dto->snapshotData);
        if ($authorId === null && is_string($dto->createdBy) && $dto->createdBy !== '') {
            $authorId = $dto->createdBy;
        }

        $reviewerNames = [];
        foreach (TemplateVersionSnapshotParser::reviewerIds($dto->snapshotData) as $uid) {
            $name = $this->resolveUserNameById($uid);
            if ($name !== null) {
                $reviewerNames[] = $name;
            }
        }

        return [
            'id' => $dto->id,
            'template_id' => $dto->versionableId,
            'version_number' => $dto->versionNumber,
            'published_at' => $dto->publishedAt,
            'published_by' => $publishedBy,
            'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
            'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
            'reviewer_names' => $reviewerNames,
            'changelog' => $dto->changelog,
        ];
    }
}
