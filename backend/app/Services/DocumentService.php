<?php

namespace App\Services;

use App\Events\DocumentStateChanged;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function findOrFail(string $id): Document
    {
        return $this->documentRepository->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = []): Document
    {
        $document  = $this->documentRepository->findOrFail($documentId);
        $oldStatus = $document->status;

        $document->update(array_merge(['status' => $newStatus], $extraAttributes));

        event(new DocumentStateChanged(
            document:  $document->fresh(),
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId:   $actorId,
        ));

        return $document->fresh();
    }

    public function submitToReview(string $documentId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo los documentos en borrador pueden enviarse a revisión.'],
            ]);
        }

        return DB::transaction(function () use ($documentId, $actorId, $document) {
            $this->documentRepository->deleteReviewsForDocument($documentId);

            $document->loadMissing('template.reviewers');

            $document = $this->transition($documentId, 'in_review', $actorId, [
                'submitted_at' => now(),
            ]);

            $rows = $document->template?->reviewers
                ?->sortBy('stage')
                ->map(fn ($r) => [
                    'reviewer_id' => $r->user_id,
                    'stage'       => $r->stage,
                ])
                ->values()
                ->all() ?? [];

            if ($rows !== []) {
                $this->documentRepository->createPendingReviews($documentId, $rows);
            }

            return $document;
        });
    }

    public function publishDocument(string $documentId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->status !== 'in_review') {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede publicar un documento en revisión.'],
            ]);
        }

        if ($this->documentRepository->countPendingReviewsForDocument($documentId) > 0) {
            throw ValidationException::withMessages([
                'reviews' => ['Quedan revisiones pendientes.'],
            ]);
        }

        return $this->transition($documentId, 'published', $actorId, [
            'published_at' => now(),
        ]);
    }

    public function rejectDocument(string $documentId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if (! in_array($document->status, ['in_review', 'published'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede rechazar un documento en revisión o publicado.'],
            ]);
        }

        return DB::transaction(function () use ($documentId, $actorId) {
            $this->documentRepository->deleteReviewsForDocument($documentId);

            return $this->transition($documentId, 'draft', $actorId, [
                'submitted_at' => null,
                'published_at' => null,
            ]);
        });
    }

    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->owner_id !== $actorId) {
            throw ValidationException::withMessages([
                'owner' => ['Solo el titular actual puede delegar el documento.'],
            ]);
        }

        if ($newOwnerId === $document->owner_id) {
            throw ValidationException::withMessages([
                'new_owner_id' => ['El nuevo titular debe ser distinto del actual.'],
            ]);
        }

        $document->update(['owner_id' => $newOwnerId]);

        return $document->fresh();
    }

    public function listReviews(string $documentId): Collection
    {
        $this->documentRepository->findOrFail($documentId);

        return $this->documentRepository->listReviewsForDocument($documentId);
    }

    public function approveReview(string $documentId, string $reviewId, string $actorId): Document
    {
        return DB::transaction(function () use ($documentId, $reviewId, $actorId) {
            $document = $this->documentRepository->findOrFail($documentId);

            if ($document->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Las revisiones solo aplican a documentos en revisión.'],
                ]);
            }

            $review = $this->documentRepository->findReviewInDocument($reviewId, $documentId);

            if ($review === null) {
                abort(404);
            }

            if ($review->reviewer_id !== $actorId) {
                abort(403, 'No eres el revisor asignado a esta etapa.');
            }

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => ['Esta revisión ya fue procesada.'],
                ]);
            }

            $review->status       = 'approved';
            $review->reviewed_at  = now();
            $this->documentRepository->saveReview($review);

            if ($this->documentRepository->countPendingReviewsForDocument($documentId) === 0) {
                return $this->transition($documentId, 'published', $actorId, [
                    'published_at' => now(),
                ]);
            }

            return $this->documentRepository->findOrFail($documentId);
        });
    }

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
                abort(404);
            }

            if ($review->reviewer_id !== $actorId) {
                abort(403, 'No eres el revisor asignado a esta etapa.');
            }

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => ['Esta revisión ya fue procesada.'],
                ]);
            }

            $review->status            = 'rejected';
            $review->rejection_reason  = $reason;
            $review->reviewed_at       = now();
            $this->documentRepository->saveReview($review);

            $this->documentRepository->deleteReviewsForDocument($documentId);

            return $this->transition($documentId, 'draft', $actorId, [
                'submitted_at' => null,
                'published_at' => null,
            ]);
        });
    }
}
