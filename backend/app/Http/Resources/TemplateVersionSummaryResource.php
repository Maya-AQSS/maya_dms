<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Versioning\TemplateVersionSummaryDto;
use App\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Historial de versiones publicadas (sin el JSONB de bloques). Los nombres de
 * autor/revisores se resuelven en el Service
 * ({@see TemplateService::listPublishedVersionSummaries});
 * el Resource solo mapea el DTO ya resuelto.
 *
 * @property TemplateVersionSummaryDto $resource
 */
class TemplateVersionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TemplateVersionSummaryDto $dto */
        $dto = $this->resource;

        return [
            'id' => $dto->id,
            'template_id' => $dto->templateId,
            'version_number' => $dto->versionNumber,
            'published_at' => $dto->publishedAt,
            'published_by' => $dto->publishedBy,
            'published_by_name' => $dto->publishedByName,
            'author_name' => $dto->authorName,
            'reviewer_names' => $dto->reviewerNames,
            'changelog' => $dto->changelog,
        ];
    }
}
