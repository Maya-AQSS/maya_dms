<?php

namespace App\Services;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Validation\ValidationException;

class TemplateReviewService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplatePublishingService $templatePublishingService,
    ) {}

    /**
     * Envia el borrador a revisión.
     */
    public function submitForReview(string $templateId, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($templateId, $actorId) {
            $template = $this->templateRepository->findOrFail($templateId);

            if ($template->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['Solo las plantillas en borrador pueden enviarse a revisión.'],
                ]);
            }

            if ($template->blocks()->doesntExist()) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque antes de enviarse a revisión.'],
                ]);
            }

            $template->loadMissing(['blocks']);

            $hasEditableBlock = $template->blocks->contains(
                fn ($b) => in_array((string) $b->block_state, ['editable', 'modifiable'], true)
            );
            if (! $hasEditableBlock) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque editable o modificable.'],
                ]);
            }

            $emptyLockedBlock = $template->blocks->first(
                fn ($b) => (string) $b->block_state === 'locked'
                    && (is_null($b->default_content) || (is_array($b->default_content) && count($b->default_content) === 0))
            );
            if ($emptyLockedBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques bloqueados no pueden estar vacíos.'],
                ]);
            }

            if ($template->reviewers()->doesntExist()) {
                if ($template->visibility_level !== TemplateVisibilityLevel::Personal) {
                    throw ValidationException::withMessages([
                        'reviewers' => ['Las plantillas no personales requieren al menos un revisor asignado antes de enviarse a revisión.'],
                    ]);
                }

                return $this->templatePublishingService->publishWithSnapshot($templateId, 'Publicación automática', $actorId);
            }

            $template->reviewers()->update(['status' => 'pending']);

            return $this->templatePublishingService->transitionStatus($template, 'in_review', $actorId);
        });
    }

    /**
     * Rechaza la revisión de la plantilla.
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

            $template->reviewers()
                ->where('user_id', $actorId)
                ->update(['status' => 'rejected']);

            return $this->templatePublishingService->transitionStatus($template, 'draft', $actorId);
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

            if ($template->review_mode === 'sequential') {
                $pendingPreviousStage = $template->reviewers()
                    ->where('stage', '<', $reviewer->stage)
                    ->where('status', '!=', 'approved')
                    ->exists();

                if ($pendingPreviousStage) {
                    throw ValidationException::withMessages([
                        'stage' => ['Debes esperar a que los revisores de etapas anteriores aprueben primero.'],
                    ]);
                }
            }

            $template->reviewers()
                ->where('user_id', $actorId)
                ->update(['status' => 'approved']);

            $allApproved = ! $template->reviewers()
                ->where('status', '!=', 'approved')
                ->exists();

            if ($allApproved) {
                return $this->templatePublishingService->publishWithSnapshot(
                    $templateId,
                    'Aprobado por todos los revisores.',
                    $actorId
                );
            }

            return $template->fresh();
        });
    }

}
