<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\DTOs\Templates\TemplateDto;
use App\Enums\BlockType;
use App\Enums\TemplateVisibilityLevel;
use App\Events\TemplateReviewApproved;
use App\Events\TemplateSubmittedForReview;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateReviewerRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Concerns\NotifiesOwner;
use App\Support\ReviewValidationNotificationRecipients;
use App\Support\ReviewValidationNotifier;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\NotificationPublisher;

class TemplateReviewService
{
    use NotifiesOwner;

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
     * Devuelve TemplateDto; los atributos derivados de presentación (can_clone,
     * can_view_history, can_create_new_version) se adjuntan sobre el Model vía el
     * callback `$beforeMap` justo antes de la conversión.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function submitForReview(string $templateId, string $actorId, string $changelog, ?callable $beforeMap = null): TemplateDto
    {
        $normalizedChangelog = VersionSubmissionChangelog::normalize($changelog);

        return $this->templateRepository->transaction(function () use ($templateId, $actorId, $normalizedChangelog, $beforeMap) {
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

            $blockPayloads = $this->templateReviewerRepository->blockPayloadSnapshot((string) $template->getKey());
            $blocksSnapshot = array_map(
                fn (TemplateBlockPayloadDto $b): array => $b->toArray(),
                $blockPayloads,
            );

            // La relación queda cargada para la conversión a DTO posterior; la
            // mutación del ciclo se persiste vía repositorio.
            $this->templateRepository->loadHeadVersion($template);
            $this->templateRepository->appendHeadVersionSubmissionCycle($templateId, $actorId, $blocksSnapshot);

            $hasEditableBlock = collect($blockPayloads)->contains(
                fn (TemplateBlockPayloadDto $b) => in_array((string) $b->blockState, ['editable', 'modifiable'], true)
            );
            if (! $hasEditableBlock) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque editable o modificable.'],
                ]);
            }

            $isEmptyContent = fn (TemplateBlockPayloadDto $b) => is_null($b->defaultContent)
                || (is_array($b->defaultContent) && count($b->defaultContent) === 0);
            // Los bloques estructurales sin cuerpo (hoja en blanco) están exentos
            // de la invariante de "no vacío". Fuente: BlockType::requiresBodyContent().
            $requiresContent = fn (TemplateBlockPayloadDto $b) => ! ($b->blockType instanceof BlockType)
                || $b->blockType->requiresBodyContent();

            $emptyModifiableBlock = collect($blockPayloads)->first(
                fn (TemplateBlockPayloadDto $b) => (string) $b->blockState === 'modifiable' && $isEmptyContent($b) && $requiresContent($b)
            );
            if ($emptyModifiableBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques modificables no pueden estar vacíos: el contenido predeterminado es obligatorio.'],
                ]);
            }

            $emptyLockedBlock = collect($blockPayloads)->first(
                fn (TemplateBlockPayloadDto $b) => (string) $b->blockState === 'locked' && $isEmptyContent($b) && $requiresContent($b)
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

                return $this->templatePublishingService->publishWithSnapshot($templateId, $normalizedChangelog, $actorId, $beforeMap);
            }

            if ($template->visibility_level !== TemplateVisibilityLevel::Personal
                && $this->templateRepository->doesntHaveDocumentReviewers($templateId)) {
                throw ValidationException::withMessages([
                    'document_reviewers' => ['Las plantillas no personales requieren al menos un validador de documento asignado antes de enviarse a revisión.'],
                ]);
            }

            $this->templateRepository->updateReviewersStatus($templateId, 'pending');

            // Mismo payload de bloques calculado arriba (no hay mutaciones entre medias).
            $this->templateRepository->loadHeadVersion($template);
            $this->templateRepository->recordHeadVersionBlocksAtSubmission($templateId, $blocksSnapshot);

            $inReview = $this->templatePublishingService->transitionStatus($template, 'in_review', $actorId);

            $this->notifyTemplateValidationRequested($inReview);

            $this->templateRepository->loadReviewers($inReview);
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

            if ($beforeMap !== null) {
                $beforeMap($inReview);
            }

            return TemplateDto::fromModel($inReview);
        });
    }

    /**
     * Rechaza la revisión de la plantilla.
     *
     * En modo secuencial solo puede actuar el revisor de la etapa pendiente activa.
     * Devuelve TemplateDto; presentación derivada vía `$beforeMap` (ver submitForReview).
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function rejectReview(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        return $this->templateRepository->transaction(function () use ($templateId, $actorId, $beforeMap) {
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
                $this->notifyOwner(
                    recipientId: $createdBy,
                    type: 'template.rejected',
                    title: 'Revisión de plantilla rechazada',
                    body: 'La revisión de la plantilla "'.$rejected->name.'" ha sido rechazada',
                    titleKey: 'notifications.template.rejected.title',
                    bodyKey: 'notifications.template.rejected.body',
                    params: ['template_id' => (string) $rejected->id, 'template_name' => $rejected->name],
                    severity: 'high',
                    metadata: ['template_id' => (string) $rejected->id],
                );
            }

            if ($beforeMap !== null) {
                $beforeMap($rejected);
            }

            return TemplateDto::fromModel($rejected);
        });
    }

    /**
     * Registra la aprobación del revisor activo.
     *
     * En modo secuencial verifica que los stages anteriores hayan aprobado primero.
     * Si todos los revisores han aprobado, la plantilla se publica automáticamente.
     *
     * Devuelve TemplateDto; presentación derivada vía `$beforeMap` (ver submitForReview).
     * En aprobación intermedia la relación reviewers no se recarga —
     * TemplateDto::fromModel() detecta que no está cargada y omite el campo.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function approveReview(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        return $this->templateRepository->transaction(function () use ($templateId, $actorId, $beforeMap) {
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
                    $beforeMap,
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

            $fresh = $this->templateRepository->refresh($template);
            if ($fresh !== null && $fresh->review_mode === 'sequential') {
                $this->notifyTemplateValidationRequested($fresh);
            }

            $result = $fresh ?? $template;
            if ($beforeMap !== null) {
                $beforeMap($result);
            }

            return TemplateDto::fromModel($result);
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
        $this->templateRepository->loadReviewers($template);

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
