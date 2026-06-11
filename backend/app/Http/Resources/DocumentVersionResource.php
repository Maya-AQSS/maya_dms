<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Versioning\DocumentVersionDetailDto;
use App\DTOs\Versioning\DocumentVersionSummaryDto;
use App\Services\DocumentVersionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa los metadatos de versión devueltos por
 * {@see DocumentVersionService::listDocumentVersions()} y la
 * forma detallada de
 * {@see DocumentVersionService::findDocumentVersionDetailOrFail()}.
 *
 * Acepta DocumentVersionSummaryDto (listado) o DocumentVersionDetailDto (detalle).
 */
class DocumentVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        if ($resource instanceof DocumentVersionDetailDto) {
            return [
                'id' => $resource->id,
                'document_id' => $resource->documentId,
                'version_number' => $resource->versionNumber,
                'trigger_event' => $resource->triggerEvent,
                'triggered_by' => $resource->triggeredBy,
                'changelog' => $resource->changelog,
                'notes' => null,
                'snapshot' => null,
                'snapshot_data' => $resource->snapshotData,
                'published_by_name' => $resource->publishedByName,
                'author_name' => $resource->authorName,
                'owner_name' => $resource->ownerName,
                'reviewer_names' => $resource->reviewerNames,
                'created_at' => $resource->createdAt,
            ];
        }

        if ($resource instanceof DocumentVersionSummaryDto) {
            return [
                'id' => $resource->id,
                'document_id' => $resource->documentId,
                'version_number' => $resource->versionNumber,
                'trigger_event' => $resource->triggerEvent,
                'triggered_by' => $resource->triggeredBy,
                'changelog' => $resource->changelog,
                'notes' => $resource->notes,
                'snapshot' => null,
                'snapshot_data' => null,
                'published_by_name' => $resource->publishedByName,
                'author_name' => $resource->authorName,
                'owner_name' => null,
                'reviewer_names' => $resource->reviewerNames,
                'created_at' => $resource->createdAt,
            ];
        }

        // Fallback: legacy array shape (should not happen after migration).
        /** @var array<string, mixed> $row */
        $row = $resource;

        return [
            'id' => $row['id'] ?? null,
            'document_id' => $row['document_id'] ?? null,
            'version_number' => $row['version_number'] ?? null,
            'trigger_event' => $row['trigger_event'] ?? null,
            'triggered_by' => $row['triggered_by'] ?? null,
            'changelog' => $row['changelog'] ?? null,
            'notes' => $row['notes'] ?? null,
            'snapshot' => $row['snapshot'] ?? null,
            'snapshot_data' => $row['snapshot_data'] ?? null,
            'published_by_name' => $row['published_by_name'] ?? null,
            'author_name' => $row['author_name'] ?? null,
            'owner_name' => $row['owner_name'] ?? null,
            'reviewer_names' => $row['reviewer_names'] ?? [],
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
