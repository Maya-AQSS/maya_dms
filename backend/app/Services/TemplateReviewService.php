<?php
declare(strict_types=1);

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
    ) {
    }

    /**
     * Envia el borrador a revisión.
     */
    public function submitForReview(string $templateId, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($templateId, $actorId) {
            $template = $this->templateRepository->findOrFail($templateId);

            if (!in_array($template->status, ['draft', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Solo las plantillas en borrador o rechazadas pueden enviarse a revisión.'],
                ]);
            }

            if ($template->blocks()->doesntExist()) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque antes de enviarse a revisión.'],
                ]);
            }

            $template->load(['blocks' => fn($q) => $q->orderBy('sort_order')]);
            $blocksSnapshot = $template->blocks->map(fn($b) => [
                'id' => (string) $b->id,
                'sort_order' => (int) $b->sort_order,
                'title' => $b->title,
                'description' => $b->description,
                'default_content' => $b->default_content,
                'block_state' => $b->block_state,
            ])->values()->all();

            $headVersion = $template->headVersion;
            if ($headVersion !== null) {
                $cycles = is_array($headVersion->change_set) ? $headVersion->change_set : [];
                $cycles[] = [
                    'cycle' => count($cycles) + 1,
                    'submitted_at' => now()->toIso8601String(),
                    'submitted_by' => $actorId,
                    'blocks' => $blocksSnapshot,
                ];
                $headVersion->update(['change_set' => $cycles]);
            }

            $template->reviewers()->update(['status' => 'pending']);

            $hasEditableBlock = $template->blocks->contains(
                fn($b) => in_array((string) $b->block_state, ['editable', 'modifiable'], true)
            );
            if (!$hasEditableBlock) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque editable o modificable.'],
                ]);
            }

            $isEmptyContent = fn($b) => is_null($b->default_content)
                || (is_array($b->default_content) && count($b->default_content) === 0);

            $emptyModifiableBlock = $template->blocks->first(
                fn($b) => (string) $b->block_state === 'modifiable' && $isEmptyContent($b)
            );
            if ($emptyModifiableBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques modificables no pueden estar vacíos: el contenido predeterminado es obligatorio.'],
                ]);
            }

            $emptyLockedBlock = $template->blocks->first(
                fn($b) => (string) $b->block_state === 'locked' && $isEmptyContent($b)
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

            $template->loadMissing('headVersion');
            $headEv = $template->headVersion;
            if ($headEv !== null) {
                $blocksSnapshot = $template->blocks->map(fn($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'default_content' => $b->default_content,
                    'block_state' => (string) $b->block_state,
                    'sort_order' => (int) $b->sort_order,
                ])->values()->all();
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
                $headEv->snapshot_data = $existing;
                $headEv->save();
            }

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

            if (!$reviewer) {
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

            return $this->templatePublishingService->transitionStatus($template, 'rejected', $actorId);
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

            if (!$reviewer) {
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

            $allApproved = !$template->reviewers()
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
