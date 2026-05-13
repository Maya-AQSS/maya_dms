<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\AuditPublisher;

class DocumentReviewService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly SnapshotServiceInterface $snapshotService,
        private readonly DocumentStateService $stateService,
        private readonly AuditPublisher $auditPublisher,
    ) {}

    /**
     * @param  string  $documentId  ID ya verificado por el llamador (controller).
     * @return Collection<int, \App\Models\DocumentReview>
     */
    public function listReviews(string $documentId): Collection
    {
        return $this->documentRepository->listReviewsForDocument($documentId);
    }

    /**
     * Aprueba una revisión del documento.
     */
    public function approveReview(string $documentId, string $reviewId, string $actorId, ?string $publicationChangelog = null): Document
    {
        return DB::transaction(function () use ($documentId, $reviewId, $actorId, $publicationChangelog) {
            $document = $this->documentRepository->findOrFail($documentId);

            if ($document->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Las revisiones solo aplican a documentos en revisión.'],
                ]);
            }

            $review = $this->documentRepository->findReviewInDocument($reviewId, $documentId);

            if ($review === null) {
                throw new ModelNotFoundException('Revisión no encontrada.');
            }

            if ($review->reviewer_id !== $actorId) {
                throw new AuthorizationException('No eres el revisor asignado a esta etapa.');
            }

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => ['Esta revisión ya fue procesada.'],
                ]);
            }

            $this->assertSequentialReviewAllowsActing($document, $review);

            $review->status = 'approved';
            $review->reviewed_at = now();
            $this->documentRepository->saveReview($review);
            $this->auditPublisher->publish(
                applicationSlug: 'maya-dms',
                entityType: 'document',
                entityId: $documentId,
                action: 'review_approved',
                userId: $actorId,
                previousValue: [
                    'review_id' => (string) $review->id,
                    'stage' => (int) $review->stage,
                    'status' => 'pending',
                ],
                newValue: [
                    'review_id' => (string) $review->id,
                    'stage' => (int) $review->stage,
                    'status' => 'approved',
                ],
            );

            if ($this->documentRepository->countPendingReviewsForDocument($documentId) === 0) {
                $this->stateService->transition($documentId, 'published', $actorId);
                $changelog = $publicationChangelog !== null && trim($publicationChangelog) !== ''
                    ? trim($publicationChangelog)
                    : 'Aprobado por todos los revisores.';
                $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                    documentId: $documentId,
                    triggerEvent: 'published',
                    triggeredBy: $actorId,
                    notes: $changelog,
                ));

                return $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
            }

            return $this->documentRepository->findOrFail($documentId);
        });
    }

    /**
     * Rechaza una revisión del documento.
     */
    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): Document
    {
        return DB::transaction(function () use ($documentId, $reviewId, $actorId, $reason) {
            $document = $this->documentRepository->findOrFail($documentId);

            if ($document->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Las revisiones solo aplican a documentos en revisión.'],
                ]);
            }

            $review = $this->documentRepository->findReviewInDocument($reviewId, $documentId);

            if ($review === null) {
                throw new ModelNotFoundException('Revisión no encontrada.');
            }

            if ($review->reviewer_id !== $actorId) {
                throw new AuthorizationException('No eres el revisor asignado a esta etapa.');
            }

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => ['Esta revisión ya fue procesada.'],
                ]);
            }

            $this->assertSequentialReviewAllowsActing($document, $review);

            $review->status = 'rejected';
            $review->rejection_reason = $reason;
            $review->reviewed_at = now();
            $this->documentRepository->saveReview($review);
            $this->auditPublisher->publish(
                applicationSlug: 'maya-dms',
                entityType: 'document',
                entityId: $documentId,
                action: 'review_rejected',
                userId: $actorId,
                previousValue: [
                    'review_id' => (string) $review->id,
                    'stage' => (int) $review->stage,
                    'status' => 'pending',
                ],
                newValue: [
                    'review_id' => (string) $review->id,
                    'stage' => (int) $review->stage,
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                ],
            );

            $this->stateService->transition($documentId, 'draft', $actorId);

            $this->documentRepository->deletePendingReviewsForDocument($documentId);

            return $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
        });
    }

    /**
     * Verifica si la revisión secuencial permite actuar.
     */
    private function assertSequentialReviewAllowsActing(Document $document, DocumentReview $review): void
    {
        $mode = $this->resolveReviewMode($document);
        if ($mode !== 'sequential') {
            return;
        }

        $minStage = $this->documentRepository->minPendingReviewStageForDocument($document->id);
        if ($minStage === null) {
            return;
        }

        if ($review->stage !== $minStage) {
            throw ValidationException::withMessages([
                'review' => ['En revisión secuencial, solo puede actuar la etapa pendiente más baja.'],
            ]);
        }
    }

    /**
     * Resuelve el modo de revisión del documento desde el snapshot anclado, con fallback a la plantilla live.
     */
    public function resolveReviewMode(Document $document): string
    {
        $templateVersionId = is_string($document->template_version_id) ? trim($document->template_version_id) : '';
        $templateId = is_string($document->template_id) ? trim($document->template_id) : '';

        if ($templateVersionId !== '' && $templateId !== '') {
            $anchor = $this->entityVersionRepository->findPublishedByIdForVersionable(
                $templateVersionId,
                Template::class,
                $templateId,
            );
            $anchoredMode = is_array($anchor?->snapshot_data)
                ? data_get($anchor->snapshot_data, 'template.review_mode')
                : null;
            if (is_string($anchoredMode) && in_array($anchoredMode, ['sequential', 'parallel'], true)) {
                return $anchoredMode;
            }
        }

        $document->loadMissing('template');
        return (string) ($document->template?->review_mode ?? 'parallel');
    }
}
