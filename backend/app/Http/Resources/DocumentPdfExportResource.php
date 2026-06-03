<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\DocumentPdfExportStatusDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DocumentPdfExportStatusDto $resource
 */
class DocumentPdfExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DocumentPdfExportStatusDto $dto */
        $dto = $this->resource;

        return [
            'state' => $dto->state,
            'document_id' => $dto->documentId,
            'path' => $dto->path,
            'queued_at' => $dto->queuedAt,
            'completed_at' => $dto->completedAt,
            'error' => $dto->error,
        ];
    }
}
