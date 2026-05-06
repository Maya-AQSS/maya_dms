<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentReviewService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly SnapshotServiceInterface $snapshotService,
        private readonly DocumentStateService $stateService,
        private readonly EntityVersionLifecycleServiceInterface $entityVersionLifecycleService,
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

            if ($this->documentRepository->countPendingReviewsForDocument($documentId) === 0) {
                $this->stateService->transition($documentId, 'published', $actorId, [
                    'published_at' => now(),
                ]);
                $changelog = $publicationChangelog !== null && trim($publicationChangelog) !== ''
                    ? trim($publicationChangelog)
                    : 'Publicado tras aprobación de revisión.';
                $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                    documentId: $documentId,
                    triggerEvent: 'published',
                    triggeredBy: $actorId,
                    notes: $changelog,
                ));
                $latestVersion = $this->documentRepository->findLatestDocumentVersionOrFail($documentId);

                $this->entityVersionLifecycleService->createPublishedSnapshotVersion(
                    Document::class,
                    $documentId,
                    (int) $latestVersion->version_number,
                    is_array($latestVersion->snapshot_data) ? $latestVersion->snapshot_data : [],
                    $actorId,
                    $changelog,
                );

                return $this->documentRepository->findOrFail($documentId);
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

            $updated = $this->stateService->transition($documentId, 'draft', $actorId, [
                'submitted_at' => null,
                'published_at' => null,
            ]);

            $this->documentRepository->deletePendingReviewsForDocument($documentId);

            return $updated;
        });
    }

    /**
     * Verifica si la revisión secuencial permite actuar.
     */
    private function assertSequentialReviewAllowsActing(Document $document, DocumentReview $review): void
    {
        $document->loadMissing('template');
        $mode = $document->template?->review_mode ?? 'parallel';
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
}
