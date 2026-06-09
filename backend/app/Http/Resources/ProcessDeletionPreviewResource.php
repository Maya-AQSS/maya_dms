<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Processes\ProcessDeletionPreviewDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProcessDeletionPreviewDto $resource
 */
class ProcessDeletionPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ProcessDeletionPreviewDto $dto */
        $dto = $this->resource;

        return [
            'templates_count' => $dto->templatesCount,
            'documents_count' => $dto->documentsCount,
            'subprocess_count' => $dto->subprocessCount,
        ];
    }
}
