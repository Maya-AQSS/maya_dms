<?php

namespace App\Services;

use App\Events\TemplateStateChanged;
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
        $template = $this->templateRepository->findOrFail($templateId);

        if ($template->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo las plantillas en borrador pueden enviarse a revisión.'],
            ]);
        }

        if ($template->reviewers()->doesntExist()) {
            return $this->templatePublishingService->publishWithSnapshot($templateId, null, $actorId);
        }

        $template->reviewers()->update(['status' => 'pending']);

        return $this->updateTemplateStatusWithEvent($template, 'in_review', $actorId);
    }

    /**
     * Rechaza la revisión de la plantilla.
     */
    public function rejectReview(string $templateId, string $actorId): Template
    {
        $template = $this->templateRepository->findOrFail($templateId);

        if ($template->status !== 'in_review') {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede rechazar una plantilla en revisión.'],
            ]);
        }

        $template->reviewers()
            ->where('user_id', $actorId)
            ->update(['status' => 'rejected']);

        return $this->updateTemplateStatusWithEvent($template, 'draft', $actorId);
    }

    /**
     * Registra la aprobación del revisor activo.
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

            $allApproved = $template->reviewers()
                ->where('status', '!=', 'approved')
                ->doesntExist();

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

    private function updateTemplateStatusWithEvent(Template $template, string $newStatus, string $actorId): Template
    {
        $oldStatus = $template->status;
        $updated = $this->templateRepository->update($template, ['status' => $newStatus]);
        event(new TemplateStateChanged(
            template: $updated,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId: $actorId,
        ));

        return $updated;
    }
}
