<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\DTOs\Documents\DocumentDto;
use App\DTOs\Documents\DocumentReviewDto;
use App\Events\DocumentReviewApproved;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Support\DocumentReviewModeResolver;
use App\Support\ReviewValidationNotificationRecipients;
use App\Support\ReviewValidationNotifier;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Events\BroadcastNotificationCreated;
use Maya\Messaging\Publishers\NotificationPublisher;

class DocumentReviewService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly SnapshotServiceInterface $snapshotService,
        private readonly DocumentStateService $stateService,
        private readonly NotificationPublisher $notificationPublisher,
        private readonly DocumentReviewModeResolver $documentReviewModeResolver,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
        private readonly ReviewValidationNotifier $reviewValidationNotifier,
    ) {}

    /**
     * @param  string  $documentId  ID ya verificado por el llamador (controller).
     * @return Collection<int, DocumentReviewDto>
     */
    public function listReviews(string $documentId): Collection
    {
        return $this->documentRepository->listReviewsForDocument($documentId)
            ->map(static fn (DocumentReview $r) => DocumentReviewDto::fromModel($r));
    }

    /**
     * Aprueba una revisión del documento.
     */
    public function approveReview(string $documentId, string $reviewId, string $actorId, ?string $publicationChangelog = null): DocumentDto
    {
        $approved = $this->documentRepository->transaction(function () use ($documentId, $reviewId, $actorId, $publicationChangelog) {
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

            $this->documentRepository->approveReview((string) $review->id);

            // Recargar para tener timestamp actualizado
            $review = $this->documentRepository->findReviewInDocument((string) $review->id, $documentId);

            if ($this->documentRepository->countPendingReviewsForDocument($documentId) === 0) {
                $this->documentRepository->loadHeadVersion($document);
                $changelog = VersionSubmissionChangelog::requireNonEmpty(
                    $publicationChangelog,
                    $document->headVersion?->changelog,
                );

                // Aprobación final: provoca la publicación, que se audita como
                // state_changed(published) enriquecido con la etapa y el validador.
                $this->stateService->transition(
                    $documentId,
                    'published',
                    $actorId,
                    reviewerStage: (int) $review->stage,
                    reviewerName: $this->userDirectoryRepository->findNameById($actorId),
                );
                $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                    documentId: $documentId,
                    triggerEvent: 'published',
                    triggeredBy: $actorId,
                    notes: $changelog,
                ));
                $this->documentRepository->clearHeadVersionChangelog($documentId);

                $refreshed = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
                $this->notifyDocumentPublished($refreshed);

                return $refreshed;
            }

            // Aprobación intermedia: no hay cambio de estado (sigue in_review), así que
            // este evento es la única traza de la decisión del validador.
            DocumentReviewApproved::dispatch(
                $documentId,
                $review,
                $actorId,
                $this->userDirectoryRepository->findNameById($actorId),
            );

            $refreshed = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
            if ($this->resolveReviewMode($refreshed) === 'sequential') {
                $this->notifyPendingValidationRequest($refreshed);
            }

            return $refreshed;
        });

        return DocumentDto::fromModel($approved);
    }

    private function notifyPendingValidationRequest(Document $document): void
    {
        $documentId = (string) $document->id;
        $mode = $this->resolveReviewMode($document);

        $pending = $this->documentRepository->listReviewsForDocument($documentId)
            ->filter(static fn (DocumentReview $r): bool => ($r->status ?? 'pending') === 'pending')
            ->map(static fn (DocumentReview $r): array => [
                'reviewer_id' => (string) $r->reviewer_id,
                'stage' => (int) $r->stage,
            ])
            ->values()
            ->all();

        $recipients = ReviewValidationNotificationRecipients::filterForReviewMode($mode, $pending);

        $documentTitle = $document->title;

        $this->reviewValidationNotifier->notifyEach(
            $recipients,
            'reviewer_id',
            fn (string $recipientId): array => [
                'type' => 'document.validation_requested',
                'recipientId' => $recipientId,
                'title' => 'Nueva solicitud de revisión',
                'body' => 'El documento "'.$documentTitle.'" requiere tu revisión',
                'titleKey' => 'notifications.document.validation_requested.title',
                'bodyKey' => 'notifications.document.validation_requested.body',
                'params' => ['document_id' => $documentId, 'document_title' => $documentTitle],
                'severity' => 'high',
                'channels' => ['app'],
                'metadata' => ['document_id' => $documentId],
            ],
        );
    }

    private function notifyDocumentPublished(Document $document): void
    {
        $recipientId = is_string($document->owner_id) && $document->owner_id !== ''
            ? $document->owner_id
            : (is_string($document->created_by) ? $document->created_by : null);

        if ($recipientId === null || $recipientId === '') {
            return;
        }

        $title = 'Documento publicado';
        $body = 'El documento "'.$document->title.'" ha sido publicado correctamente';
        $metadata = ['document_id' => (string) $document->id];

        try {
            $this->notificationPublisher->send(
                type: 'document.published',
                recipientId: $recipientId,
                title: $title,
                body: $body,
                titleKey: 'notifications.document.published.title',
                bodyKey: 'notifications.document.published.body',
                params: ['document_id' => (string) $document->id, 'document_title' => $document->title],
                severity: 'info',
                channels: ['app'],
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            Log::warning('notification.publish_failed', [
                'error' => $e->getMessage(),
                'type' => 'document.published',
                'document_id' => (string) $document->id,
            ]);
        }

        try {
            BroadcastNotificationCreated::dispatch(
                recipientId: $recipientId,
                app: 'maya-dms',
                type: 'document.published',
                title: $title,
                body: $body,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            Log::warning('broadcast.dispatch_failed', [
                'error' => $e->getMessage(),
                'type' => 'document.published',
                'document_id' => (string) $document->id,
            ]);
        }
    }

    private function notifyDocumentRejected(Document $document, ?string $reason): void
    {
        $recipientId = is_string($document->owner_id) && $document->owner_id !== ''
            ? $document->owner_id
            : (is_string($document->created_by) ? $document->created_by : null);

        if ($recipientId === null || $recipientId === '') {
            return;
        }

        $title = 'Revisión rechazada';
        $body = 'La revisión del documento "'.$document->title.'" ha sido rechazada'.($reason !== null && $reason !== '' ? ': '.$reason : '');
        $metadata = ['document_id' => (string) $document->id, 'reason' => $reason];

        try {
            $this->notificationPublisher->send(
                type: 'document.rejected',
                recipientId: $recipientId,
                title: $title,
                body: $body,
                titleKey: 'notifications.document.rejected.title',
                bodyKey: 'notifications.document.rejected.body',
                params: ['document_id' => (string) $document->id, 'document_title' => $document->title, 'reason' => $reason],
                severity: 'high',
                channels: ['app'],
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            Log::warning('notification.publish_failed', [
                'error' => $e->getMessage(),
                'type' => 'document.rejected',
                'document_id' => (string) $document->id,
            ]);
        }

        try {
            BroadcastNotificationCreated::dispatch(
                recipientId: $recipientId,
                app: 'maya-dms',
                type: 'document.rejected',
                title: $title,
                body: $body,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            Log::warning('broadcast.dispatch_failed', [
                'error' => $e->getMessage(),
                'type' => 'document.rejected',
                'document_id' => (string) $document->id,
            ]);
        }
    }

    /**
     * Rechaza una revisión del documento.
     */
    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): DocumentDto
    {
        $rejected = $this->documentRepository->transaction(function () use ($documentId, $reviewId, $actorId, $reason) {
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

            $this->documentRepository->rejectReview((string) $review->id, $reason);

            // Recargar para tener datos actualizados
            $review = $this->documentRepository->findReviewInDocument((string) $review->id, $documentId);

            // El rechazo cambia el estado a "rejected"; se audita como state_changed
            // enriquecido con la etapa, el validador y el motivo del rechazo.
            $this->stateService->transition(
                $documentId,
                'rejected',
                $actorId,
                reviewerStage: (int) $review->stage,
                reviewerName: $this->userDirectoryRepository->findNameById($actorId),
                rejectionReason: $reason,
            );

            $this->documentRepository->deletePendingReviewsForDocument($documentId);

            $refreshed = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
            $this->notifyDocumentRejected($refreshed, $reason);

            return $refreshed;
        });

        return DocumentDto::fromModel($rejected);
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
     * Modo de revisión: plantilla live primero, luego snapshot anclado ({@see DocumentReviewModeResolver}).
     */
    public function resolveReviewMode(Document $document): string
    {
        return $this->documentReviewModeResolver->resolve($document);
    }
}
