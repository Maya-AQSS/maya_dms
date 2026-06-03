<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Support\ReviewValidationNotificationRecipients;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\NotificationPublisher;

class TemplateReviewService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplatePublishingService $templatePublishingService,
        private readonly NotificationPublisher $notificationPublisher,
    ) {}

    /**
     * Envia el borrador a revisión.
     */
    public function submitForReview(string $templateId, string $actorId, string $changelog): Template
    {
        $normalizedChangelog = VersionSubmissionChangelog::normalize($changelog);

        return $this->templateRepository->transaction(function () use ($templateId, $actorId, $normalizedChangelog) {
            $template = $this->templateRepository->findOrFail($templateId);

            if (! in_array($template->status, ['draft', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Solo las plantillas en borrador o rechazadas pueden enviarse a revisión.'],
                ]);
            }

            if ($template->blocks()->doesntExist()) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque antes de enviarse a revisión.'],
                ]);
            }

            $template->load(['blocks' => fn ($q) => $q->orderBy('sort_order')]);
            $blocksSnapshot = $template->blocks->map(fn ($b) => [
                'id' => (string) $b->id,
                'sort_order' => (int) $b->sort_order,
                'title' => $b->title,
                'description' => $b->description,
                'default_content' => $b->default_content,
                'block_state' => $b->block_state,
            ])->values()->all();

            $template->loadMissing('headVersion');
            $headVersion = $template->headVersion;
            if ($headVersion !== null) {
                $cycles = is_array($headVersion->change_set) ? $headVersion->change_set : [];
                $cycles[] = [
                    'cycle' => count($cycles) + 1,
                    'submitted_at' => now()->toIso8601String(),
                    'submitted_by' => $actorId,
                    'blocks' => $blocksSnapshot,
                ];
                // Repository update instead of direct model mutation
                $this->templateRepository->updateHeadVersionSnapshot($templateId, ['change_set' => $cycles]);
            }

            $hasEditableBlock = $template->blocks->contains(
                fn ($b) => in_array((string) $b->block_state, ['editable', 'modifiable'], true)
            );
            if (! $hasEditableBlock) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque editable o modificable.'],
                ]);
            }

            $isEmptyContent = fn ($b) => is_null($b->default_content)
                || (is_array($b->default_content) && count($b->default_content) === 0);

            $emptyModifiableBlock = $template->blocks->first(
                fn ($b) => (string) $b->block_state === 'modifiable' && $isEmptyContent($b)
            );
            if ($emptyModifiableBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques modificables no pueden estar vacíos: el contenido predeterminado es obligatorio.'],
                ]);
            }

            $emptyLockedBlock = $template->blocks->first(
                fn ($b) => (string) $b->block_state === 'locked' && $isEmptyContent($b)
            );
            if ($emptyLockedBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques bloqueados no pueden estar vacíos.'],
                ]);
            }

            $this->templateRepository->updateHeadVersionChangelog($templateId, $normalizedChangelog);

            if ($this->templateRepository->doesntHaveReviewers($templateId)) {
                if ($template->visibility_level !== TemplateVisibilityLevel::Personal) {
                    throw ValidationException::withMessages([
                        'reviewers' => ['Las plantillas no personales requieren al menos un revisor asignado antes de enviarse a revisión.'],
                    ]);
                }

                return $this->templatePublishingService->publishWithSnapshot($templateId, $normalizedChangelog, $actorId);
            }

            if ($template->visibility_level !== TemplateVisibilityLevel::Personal
                && $this->templateRepository->doesntHaveDocumentReviewers($templateId)) {
                throw ValidationException::withMessages([
                    'document_reviewers' => ['Las plantillas no personales requieren al menos un validador de documento asignado antes de enviarse a revisión.'],
                ]);
            }

            $this->templateRepository->updateReviewersStatus($templateId, 'pending');

            // Use repository to update head version snapshot
            $blocksSnapshot = $template->blocks->map(fn ($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'default_content' => $b->default_content,
                'block_state' => (string) $b->block_state,
                'sort_order' => (int) $b->sort_order,
            ])->values()->all();

            $template->loadMissing('headVersion');
            $headEv = $template->headVersion;
            if ($headEv !== null) {
                $existing = is_array($headEv->snapshot_data) ? $headEv->snapshot_data : (array) ($headEv->snapshot_data ?? []);
                if (isset($existing['blocks_at_submission']) && is_array($existing['blocks_at_submission'])) {
                    $history = isset($existing['blocks_submission_history']) && is_array($existing['blocks_submission_history'])
                        ? $existing['blocks_submission_history']
                        : [];
                    $history[] = $existing['blocks_at_submission'];
                    $existing['blocks_submission_history'] = $history;
                    $existing['blocks_at_previous_submission'] = $existing['blocks_at_submission'];
                }
                $existing['blocks_at_submission'] = $blocksSnapshot;
                $this->templateRepository->updateHeadVersionSnapshot($templateId, $existing);
            }

            $inReview = $this->templatePublishingService->transitionStatus($template, 'in_review', $actorId);

            $this->notifyTemplateValidationRequested($inReview);

            return $inReview;
        });
    }

    /**
     * Rechaza la revisión de la plantilla.
     *
     * En modo secuencial solo puede actuar el revisor de la etapa pendiente activa.
     */
    public function rejectReview(string $templateId, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($templateId, $actorId) {
            $template = $this->templateRepository->findOrFailForUpdate($templateId);

            if ($template->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Solo se puede rechazar una plantilla en revisión.'],
                ]);
            }

            $reviewer = $template->reviewers()->where('user_id', $actorId)->first();

            if (! $reviewer) {
                throw ValidationException::withMessages([
                    'user' => ['No estás asignado como revisor de esta plantilla.'],
                ]);
            }

            if ($reviewer->status === 'approved') {
                throw ValidationException::withMessages([
                    'status' => ['No puedes rechazar una plantilla que ya has aprobado.'],
                ]);
            }

            $this->assertSequentialReviewAllowsActing($template, $reviewer);

            $template->reviewers()
                ->where('user_id', $actorId)
                ->update(['status' => 'rejected']);

            $rejected = $this->templatePublishingService->transitionStatus($template, 'rejected', $actorId);

            $createdBy = is_string($rejected->created_by) && $rejected->created_by !== '' ? $rejected->created_by : null;
            if ($createdBy !== null) {
                try {
                    $this->notificationPublisher->send(
                        type: 'template.rejected',
                        recipientId: $createdBy,
                        title: 'Revisión de plantilla rechazada',
                        body: 'La revisión de la plantilla "' . $rejected->name . '" ha sido rechazada',
                        channels: ['app'],
                        metadata: ['template_id' => (string) $rejected->id],
                    );
                } catch (\Throwable $e) {
                    Log::warning('notification.publish_failed', [
                        'error' => $e->getMessage(),
                        'type' => 'template.rejected',
                        'template_id' => (string) $rejected->id,
                    ]);
                }
            }

            return $rejected;
        });
    }

    /**
     * Registra la aprobación del revisor activo.
     *
     * En modo secuencial verifica que los stages anteriores hayan aprobado primero.
     * Si todos los revisores han aprobado, la plantilla se publica automáticamente.
     */
    public function approveReview(string $templateId, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($templateId, $actorId) {
            $template = $this->templateRepository->findOrFailForUpdate($templateId);

            if ($template->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Solo se puede aprobar una plantilla en revisión.'],
                ]);
            }

            $reviewer = $template->reviewers()
                ->where('user_id', $actorId)
                ->first();

            if (! $reviewer) {
                throw ValidationException::withMessages([
                    'user' => ['No estás asignado como revisor de esta plantilla.'],
                ]);
            }

            if ($reviewer->status === 'approved') {
                throw ValidationException::withMessages([
                    'status' => ['Ya has aprobado esta plantilla.'],
                ]);
            }

            $this->assertSequentialReviewAllowsActing($template, $reviewer);

            $template->reviewers()
                ->where('user_id', $actorId)
                ->update(['status' => 'approved']);

            $allApproved = ! $template->reviewers()
                ->where('status', '!=', 'approved')
                ->exists();

            if ($allApproved) {
                return $this->templatePublishingService->publishWithSnapshot(
                    $templateId,
                    null,
                    $actorId,
                );
            }

            $fresh = $template->fresh();
            if ($fresh !== null && $fresh->review_mode === 'sequential') {
                $this->notifyTemplateValidationRequested($fresh);
            }

            return $fresh ?? $template;
        });
    }

    /**
     * En revisión secuencial, solo actúa el revisor cuya etapa no tiene pendientes anteriores.
     */
    private function assertSequentialReviewAllowsActing(Template $template, TemplateReviewer $reviewer): void
    {
        if ($template->review_mode !== 'sequential') {
            return;
        }

        $minStage = $this->templateRepository->minPendingReviewStageForTemplate($template->id);
        if ($minStage === null) {
            return;
        }

        if ((int) $reviewer->stage !== $minStage) {
            throw ValidationException::withMessages([
                'stage' => ['Debes esperar a que los revisores de etapas anteriores aprueben primero.'],
            ]);
        }
    }

    private function notifyTemplateValidationRequested(Template $template): void
    {
        $template->loadMissing('reviewers');

        $pending = $template->reviewers
            ->filter(static fn (TemplateReviewer $r): bool => ($r->status ?? 'pending') === 'pending')
            ->map(static fn (TemplateReviewer $r): array => [
                'user_id' => (string) $r->user_id,
                'stage' => (int) $r->stage,
            ])
            ->values()
            ->all();

        $reviewMode = is_string($template->review_mode) ? $template->review_mode : 'parallel';
        $recipients = ReviewValidationNotificationRecipients::filterForReviewMode($reviewMode, $pending);

        foreach ($recipients as $row) {
            $reviewerId = $row['user_id'] ?? '';
            if ($reviewerId === '') {
                continue;
            }

            try {
                $this->notificationPublisher->send(
                    type: 'template.validation_requested',
                    recipientId: $reviewerId,
                    title: 'Nueva solicitud de revisión de plantilla',
                    body: 'La plantilla "' . $template->name . '" requiere tu revisión',
                    channels: ['app'],
                    metadata: ['template_id' => (string) $template->id],
                );
            } catch (\Throwable $e) {
                Log::warning('notification.publish_failed', [
                    'error' => $e->getMessage(),
                    'type' => 'template.validation_requested',
                    'template_id' => (string) $template->id,
                    'reviewer_id' => $reviewerId,
                ]);
            }
        }
    }
}
