<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Versioning\TemplateVersionDetailDto;
use App\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de publicación de plantilla. El snapshot de bloques y los nombres de
 * autor/revisores se resuelven en el Service
 * ({@see TemplateService::findTemplateVersionDetailOrFail});
 * el Resource solo mapea el DTO ya resuelto.
 *
 * @property TemplateVersionDetailDto $resource
 */
class TemplateVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TemplateVersionDetailDto $dto */
        $dto = $this->resource;

        return [
            'id' => $dto->id,
            'template_id' => $dto->templateId,
            'version_number' => $dto->versionNumber,
            'template_snapshot' => $dto->templateSnapshot,
            'blocks_snapshot' => $dto->blocksSnapshot,
            'changelog' => $dto->changelog,
            'published_by' => $dto->publishedBy,
            'published_by_name' => $dto->publishedByName,
            'author_name' => $dto->authorName,
            'reviewer_names' => $dto->reviewerNames,
            'published_at' => $dto->publishedAt,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}
