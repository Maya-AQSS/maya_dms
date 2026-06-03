<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use Illuminate\Support\Facades\Date;

class SnapshotService implements SnapshotServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentVersionBlockLayerWriter $documentVersionBlockLayerWriter,
        private readonly EntityVersionLifecycleServiceInterface $entityVersionLifecycleService,
    ) {}

    /**
     * Crea un snapshot de versión de documento y sincroniza capas de bloques.
     *
     * @param  CreateDocumentSnapshotDto  $dto  Datos de creación del snapshot.
     */
    public function createDocumentSnapshot(CreateDocumentSnapshotDto $dto): void
    {
        $document = $this->documentRepository->findOrFailForRefreshAfterMutation($dto->documentId);

        // document_versions has its own sequential counter (includes submitted, published, rejected rows).
        $nextHistoryNumber = $this->documentRepository->maxDocumentVersionHistoryNumber($dto->documentId) + 1;
        $snapshot = $this->buildDocumentVersionSnapshot($document, $nextHistoryNumber);

        $entityVersionId = null;
        if ($dto->triggerEvent === 'published') {
            $changelog = is_string($dto->notes) ? trim($dto->notes) : null;
            if ($changelog === '') {
                $changelog = null;
            }

            // entity_versions has a separate counter: only published snapshots appear here.
            $nextPublishedNumber = $this->documentRepository->maxDocumentVersionNumber($dto->documentId) + 1;
            $entityVersion = $this->entityVersionLifecycleService->createPublishedSnapshotVersion(
                Document::class,
                $dto->documentId,
                $nextPublishedNumber,
                $snapshot,
                $dto->triggeredBy,
                $changelog,
            );
            $entityVersionId = (string) $entityVersion->id;
        }

        $this->documentRepository->insertDocumentVersion(
            $dto->documentId,
            $nextHistoryNumber,
            $dto->triggerEvent,
            $dto->triggeredBy,
            $entityVersionId !== null ? null : $snapshot,
            $dto->notes,
            $entityVersionId,
        );

        $latestVersion = $this->documentRepository->findLatestDocumentVersionOrFail($dto->documentId);
        $this->documentVersionBlockLayerWriter->syncLayersForNewPublication((string) $latestVersion->id, $dto->documentId);
    }

    /**
     * Construye el snapshot de versión de documento.
     *
     * @param  Document  $document  El documento a snapshotear.
     * @param  int  $snapshotVersionNumber  El número de versión del snapshot.
     * @return array<string, mixed>
     */
    private function buildDocumentVersionSnapshot(Document $document, int $snapshotVersionNumber): array
    {
        $document = $this->documentRepository->findOrFailForRefreshAfterMutation((string) $document->id);
        $document->load([
            'blocks' => fn ($q) => $q->orderBy('sort_order'),
            'reviews' => fn ($q) => $q->orderBy('stage')->orderBy('created_at'),
        ]);

        $lifecycle = $this->snapshotDocumentLifecycleIso8601($document);

        return [
            'snapshot_version_number' => $snapshotVersionNumber,
            'document' => [
                'id' => $document->id,
                'process_id' => $document->process_id,
                'template_id' => $document->template_id,
                'template_version_id' => $document->template_version_id,
                'title' => $document->title,
                'delivery_deadline' => $document->delivery_deadline?->toDateString(),
                'study_type_id' => $document->study_type_id,
                'study_id' => $document->study_id,
                'module_id' => $document->module_id,
                'team_id' => $document->team_id,
                'created_by' => $document->created_by,
                'owner_id' => $document->owner_id,
                'status' => $document->status,
                'current_version' => (int) $document->current_version,
                'submitted_at' => $lifecycle['submitted_at'],
                'published_at' => $lifecycle['published_at'],
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
            'reviewers' => $document->reviews->map(static function ($r): array {
                return [
                    'reviewer_id' => (string) $r->reviewer_id,
                    'stage' => $r->stage !== null ? (int) $r->stage : null,
                    'status' => (string) ($r->status ?? 'pending'),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Valores ISO para el JSON del snapshot en el momento de la captura (antes de persistir la nueva {@see EntityVersion}).
     *
     * @return array{submitted_at: ?string, published_at: ?string}
     */
    private function snapshotDocumentLifecycleIso8601(Document $document): array
    {
        if ($document->status !== 'published') {
            return ['submitted_at' => null, 'published_at' => null];
        }

        $reviewFirst = $this->documentRepository->firstReviewCreatedAt((string) $document->id);

        $submitted = $reviewFirst !== null
            ? Date::parse($reviewFirst)->toIso8601String()
            : now()->toIso8601String();

        return [
            'submitted_at' => $submitted,
            'published_at' => now()->toIso8601String(),
        ];
    }
}
