<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;

class SnapshotService implements SnapshotServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function createDocumentSnapshot(CreateDocumentSnapshotDto $dto): void
    {
        $document = $this->documentRepository->findOrFail($dto->documentId);
        $nextNumber = $this->documentRepository->maxDocumentVersionNumber($dto->documentId) + 1;
        $snapshot = $this->buildDocumentVersionSnapshot($document, $nextNumber);

        $this->documentRepository->insertDocumentVersion(
            $dto->documentId,
            $nextNumber,
            $dto->triggerEvent,
            $dto->triggeredBy,
            $snapshot,
            $dto->notes,
        );

        $document->update(['current_version' => $nextNumber]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocumentVersionSnapshot(Document $document, int $snapshotVersionNumber): array
    {
        $document->loadMissing(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

        return [
            'snapshot_version_number' => $snapshotVersionNumber,
            'document' => [
                'id' => $document->id,
                'template_id' => $document->template_id,
                'template_version_id' => $document->template_version_id,
                'title' => $document->title,
                'study_type_id' => $document->study_type_id,
                'study_id' => $document->study_id,
                'module_id' => $document->module_id,
                'created_by' => $document->created_by,
                'owner_id' => $document->owner_id,
                'status' => $document->status,
                'current_version' => (int) $document->current_version,
                'submitted_at' => $document->submitted_at?->toIso8601String(),
                'published_at' => $document->published_at?->toIso8601String(),
            ],
            'blocks' => $document->blocks->map(static function ($b): array {
                return [
                    'id' => $b->id,
                    'template_block_id' => $b->template_block_id,
                    'content' => $b->content,
                    'is_filled' => (bool) $b->is_filled,
                    'sort_order' => (int) $b->sort_order,
                    'last_edited_by' => $b->last_edited_by,
                    'locked_by' => $b->locked_by,
                    'locked_at' => $b->locked_at?->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }
}
