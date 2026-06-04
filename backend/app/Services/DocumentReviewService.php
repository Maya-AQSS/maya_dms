<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\Events\DocumentReviewApproved;
use App\Events\DocumentReviewRejected;
use Maya\Messaging\Events\BroadcastNotificationCreated;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Support\DocumentReviewModeResolver;
use App\Support\ReviewValidationNotificationRecipients;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
    ) {}

    /**
     * @param  string  $documentId  ID ya verificado por el llamador (controller).
     * @return Collection<int, DocumentReview>
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

            $this->documentRepository->approveReview((string) $review->id);

            // Recargar para tener timestamp actualizado
            $review = $this->documentRepository->findReviewInDocument((string) $review->id, $documentId);
            DocumentReviewApproved::dispatch($documentId, $review, $actorId);

            if ($this->documentRepository->countPendingReviewsForDocument($documentId) === 0) {
                $document->loadMissing('headVersion');
                $changelog = \App\Support\VersionSubmissionChangelog::requireNonEmpty(
                    $publicationChangelog,
                    $document->headVersion?->changelog,
                );

                $this->stateService->transition($documentId, 'published', $actorId);
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

            $refreshed = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
            if ($this->resolveReviewMode($refreshed) === 'sequential') {
                $this->notifyPendingValidationRequest($refreshed);
            }

            return $refreshed;
        });
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

        foreach ($recipients as $row) {
            $reviewerId = $row['reviewer_id'] ?? '';
            if ($reviewerId === '') {
                continue;
            }

            try {
                $this->notificationPublisher->send(
                    type: 'document.validation_requested',
                    recipientId: $reviewerId,
                    title: 'Nueva solicitud de revisión',
                    body: 'El documento "' . $document->title . '" requiere tu revisión',
                    titleKey: 'notifications.document.validation_requested.title',
                    bodyKey: 'notifications.document.validation_requested.body',
                    params: ['document_id' => $documentId, 'document_title' => $document->title],
                    severity: 'high',
                    channels: ['app'],
                    metadata: ['document_id' => $documentId],
                );
            } catch (\Throwable $e) {
                Log::warning('notification.publish_failed', [
                    'error' => $e->getMessage(),
                    'type' => 'document.validation_requested',
                    'document_id' => $documentId,
                    'reviewer_id' => $reviewerId,
                ]);
            }
        }
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
        $body = 'El documento "' . $document->title . '" ha sido publicado correctamente';
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
        $body = 'La revisión del documento "' . $document->title . '" ha sido rechazada' . ($reason !== null && $reason !== '' ? ': ' . $reason : '');
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

            $this->documentRepository->rejectReview((string) $review->id, $reason);

            // Recargar para tener datos actualizados
            $review = $this->documentRepository->findReviewInDocument((string) $review->id, $documentId);
            DocumentReviewRejected::dispatch($documentId, $review, $actorId, $reason);

            $this->stateService->transition($documentId, 'rejected', $actorId);

            $this->documentRepository->deletePendingReviewsForDocument($documentId);

            $refreshed = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
            $this->notifyDocumentRejected($refreshed, $reason);

            return $refreshed;
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
     * Modo de revisión: plantilla live primero, luego snapshot anclado ({@see DocumentReviewModeResolver}).
     */
    public function resolveReviewMode(Document $document): string
    {
        return $this->documentReviewModeResolver->resolve($document);
    }
}
