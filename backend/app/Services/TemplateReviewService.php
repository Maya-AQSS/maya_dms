<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\Enums\BlockType;
use App\Enums\TemplateVisibilityLevel;
use App\Events\TemplateReviewApproved;
use App\Events\TemplateSubmittedForReview;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateReviewerRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Support\ReviewValidationNotificationRecipients;
use App\Support\ReviewValidationNotifier;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Events\BroadcastNotificationCreated;
use Maya\Messaging\Publishers\NotificationPublisher;

class TemplateReviewService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateReviewerRepositoryInterface $templateReviewerRepository,
        private readonly TemplatePublishingService $templatePublishingService,
        private readonly NotificationPublisher $notificationPublisher,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
        private readonly ReviewValidationNotifier $reviewValidationNotifier,
    ) {}

    /**
     * Envía el borrador a revisión.
     *
     * Excepción B4 consciente: devuelve el Model Eloquent (no TemplateDto) porque
     * TemplateStateController::submitForReview adjunta atributos derivados vía
     * setAttribute() (can_clone, can_view_history, can_create_new_version) sobre
     * el modelo antes de convertirlo a TemplateDto::fromModel(). El DTO es readonly
     * y no admite esas mutaciones post-construcción.
     *
     * Relaciones garantizadas en el retorno: reviewers, headVersion (via loadMissing).
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

            if (! $this->templateReviewerRepository->templateHasBlocks((string) $template->getKey())) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque antes de enviarse a revisión.'],
                ]);
            }

            $this->templateReviewerRepository->loadBlocksForTemplate($template);
            $blocksSnapshot = $template->blocks
                ->map(fn ($b) => TemplateBlockPayloadDto::fromModel($b)->toArray())
                ->values()->all();

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
            // Los bloques estructurales sin cuerpo (hoja en blanco) están exentos
            // de la invariante de "no vacío". Fuente: BlockType::requiresBodyContent().
            $requiresContent = fn ($b) => ! ($b->block_type instanceof BlockType)
                || $b->block_type->requiresBodyContent();

            $emptyModifiableBlock = $template->blocks->first(
                fn ($b) => (string) $b->block_state === 'modifiable' && $isEmptyContent($b) && $requiresContent($b)
            );
            if ($emptyModifiableBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques modificables no pueden estar vacíos: el contenido predeterminado es obligatorio.'],
                ]);
            }

            $emptyLockedBlock = $template->blocks->first(
                fn ($b) => (string) $b->block_state === 'locked' && $isEmptyContent($b) && $requiresContent($b)
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
            $blocksSnapshot = $template->blocks
                ->map(fn ($b) => TemplateBlockPayloadDto::fromModel($b)->toArray())
                ->values()->all();

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

            $inReview->loadMissing('reviewers');
            $visibility = $inReview->visibility_level instanceof TemplateVisibilityLevel
                ? $inReview->visibility_level->value
                : (is_string($inReview->visibility_level) ? $inReview->visibility_level : null);

            TemplateSubmittedForReview::dispatch(
                $templateId,
                $actorId,
                is_string($inReview->review_mode) ? $inReview->review_mode : 'parallel',
                $inReview->reviewers
                    ->map(fn (TemplateReviewer $r): array => [
                        'id' => (string) $r->user_id,
                        'name' => $this->userDirectoryRepository->findNameById((string) $r->user_id),
                        'stage' => (int) $r->stage,
                    ])
                    ->values()
                    ->all(),
                $inReview->name,
                $visibility,
                $inReview->study_type_id,
                $inReview->study_id,
                $inReview->module_id,
                $normalizedChangelog,
            );

            return $inReview;
        });
    }

    /**
     * Rechaza la revisión de la plantilla.
     *
     * En modo secuencial solo puede actuar el revisor de la etapa pendiente activa.
     *
     * Excepción B4 consciente: devuelve el Model Eloquent por la misma razón que
     * submitForReview — el controller adjunta can_clone/can_view_history/can_create_new_version
     * vía setAttribute() antes de la conversión a TemplateDto::fromModel().
     *
     * Relaciones garantizadas en el retorno: ninguna adicional; el modelo devuelto
     * por transitionStatus es el modelo post-transición (fresh o actualizado).
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

            $reviewer = $this->templateReviewerRepository->findReviewerForTemplate(
                (string) $template->getKey(),
                $actorId,
            );

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

            $this->templateReviewerRepository->updateReviewerStatus(
                (string) $template->getKey(),
                $actorId,
                'rejected',
            );

            $rejected = $this->templatePublishingService->transitionStatus(
                $template,
                'rejected',
                $actorId,
                reviewerStage: (int) $reviewer->stage,
                reviewerName: $this->userDirectoryRepository->findNameById($actorId),
            );

            $createdBy = is_string($rejected->created_by) && $rejected->created_by !== '' ? $rejected->created_by : null;
            if ($createdBy !== null) {
                try {
                    $this->notificationPublisher->send(
                        type: 'template.rejected',
                        recipientId: $createdBy,
                        title: 'Revisión de plantilla rechazada',
                        body: 'La revisión de la plantilla "'.$rejected->name.'" ha sido rechazada',
                        titleKey: 'notifications.template.rejected.title',
                        bodyKey: 'notifications.template.rejected.body',
                        params: ['template_id' => (string) $rejected->id, 'template_name' => $rejected->name],
                        severity: 'high',
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

                try {
                    BroadcastNotificationCreated::dispatch(
                        recipientId: $createdBy,
                        app: 'maya-dms',
                        type: 'template.rejected',
                        title: 'Revisión de plantilla rechazada',
                        body: 'La revisión de la plantilla "'.$rejected->name.'" ha sido rechazada',
                        metadata: ['template_id' => (string) $rejected->id],
                    );
                } catch (\Throwable $e) {
                    Log::warning('broadcast.dispatch_failed', [
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
     *
     * Excepción B4 consciente: devuelve el Model Eloquent por la misma razón que
     * submitForReview — el controller adjunta can_clone/can_view_history/can_create_new_version
     * vía setAttribute() antes de la conversión a TemplateDto::fromModel().
     *
     * Relaciones garantizadas en el retorno: headVersion (via publishWithSnapshot o
     * fresh()); en aprobación intermedia la relación reviewers no se recarga —
     * TemplateDto::fromModel() detecta que no está cargada y omite el campo.
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

            $reviewer = $this->templateReviewerRepository->findReviewerForTemplate(
                (string) $template->getKey(),
                $actorId,
            );

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

            $this->templateReviewerRepository->updateReviewerStatus(
                (string) $template->getKey(),
                $actorId,
                'approved',
            );

            $allApproved = $this->templateReviewerRepository->allReviewersApproved((string) $template->getKey());

            if ($allApproved) {
                // Aprobación final: provoca la publicación, que ya se audita como
                // state_changed(published) enriquecido en publishWithSnapshot.
                return $this->templatePublishingService->publishWithSnapshot(
                    $templateId,
                    null,
                    $actorId,
                );
            }

            // Aprobación intermedia: no hay cambio de estado (sigue in_review), así que
            // este evento es la única traza de la decisión del validador.
            TemplateReviewApproved::dispatch(
                $templateId,
                $reviewer,
                $actorId,
                $this->userDirectoryRepository->findNameById($actorId),
            );

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

        $templateId = (string) $template->id;
        $templateName = $template->name;

        $this->reviewValidationNotifier->notifyEach(
            $recipients,
            'user_id',
            fn (string $recipientId): array => [
                'type' => 'template.validation_requested',
                'recipientId' => $recipientId,
                'title' => 'Nueva solicitud de revisión de plantilla',
                'body' => 'La plantilla "'.$templateName.'" requiere tu revisión',
                'titleKey' => 'notifications.template.validation_requested.title',
                'bodyKey' => 'notifications.template.validation_requested.body',
                'params' => ['template_id' => $templateId, 'template_name' => $templateName],
                'severity' => 'high',
                'channels' => ['app'],
                'metadata' => ['template_id' => $templateId],
            ],
        );
    }
}
