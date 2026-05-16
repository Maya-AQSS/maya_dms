<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\DocumentVersionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa los metadatos de versión devueltos por
 * {@see DocumentVersionService::listDocumentVersions()} y la
 * forma detallada de
 * {@see DocumentVersionService::findDocumentVersionDetailOrFail()}.
 */
class DocumentVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'id' => $row['id'] ?? null,
            'document_id' => $row['document_id'] ?? null,
            'version_number' => $row['version_number'] ?? null,
            'trigger_event' => $row['trigger_event'] ?? null,
            'triggered_by' => $row['triggered_by'] ?? null,
            'changelog' => $row['changelog'] ?? null,
            'notes' => $row['notes'] ?? null,
            'snapshot' => $row['snapshot'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
