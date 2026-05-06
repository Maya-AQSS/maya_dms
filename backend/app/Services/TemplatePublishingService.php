<?php

namespace App\Services;

use App\Events\TemplateStateChanged;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use Illuminate\Validation\ValidationException;

class TemplatePublishingService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
        private readonly EntityVersionLifecycleServiceInterface $entityVersionLifecycleService,
        private readonly TemplateVersionBlockLayerWriter $templateVersionBlockLayerWriter,
    ) {}

    /**
     * Actualiza el estado de una plantilla y emite el evento de dominio TemplateStateChanged.
     *
     * Método compartido por TemplateService y TemplateReviewService para evitar duplicación.
     */
    public function transitionStatus(Template $template, string $newStatus, string $actorId): Template
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

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     *
     * Si el actor es un revisor asignado en modo secuencial, verifica que todos los
     * stages anteriores hayan aprobado antes de permitir la publicación. Esto garantiza
     * que el endpoint /publish respeta el mismo orden de etapas que approveReview.
     * Cuando el actor es el creador (publicación directa sin revisores), la validación
     * se omite porque no existe ningún revisor con stage previo.
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($templateId, $changelog, $actorId) {
            $template = $this->templateRepository->findOrFailForUpdate($templateId);

            if (! in_array($template->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Solo se puede publicar una plantilla en borrador o en revisión.'],
                ]);
            }

            $reviewer = $template->reviewers()->where('user_id', $actorId)->first();

            if ($reviewer && $template->review_mode === 'sequential') {
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

            $template->load([
                'blocks' => fn ($q) => $q->orderBy('sort_order'),
                'reviewers' => fn ($q) => $q->orderBy('stage')->orderBy('user_id'),
                'documentReviewers' => fn ($q) => $q->orderBy('created_at')->orderBy('user_id'),
            ]);

            if ($template->blocks->isEmpty()) {
                throw ValidationException::withMessages([
                    'blocks' => ['La plantilla debe tener al menos un bloque antes de publicarse.'],
                ]);
            }

            $blocksSnapshot = $template->blocks->map(fn ($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'description' => $b->description,
                'default_content' => $b->default_content,
                'block_state' => $b->block_state,
                'sort_order' => $b->sort_order,
            ])->values()->all();
            $templateReviewersSnapshot = $template->reviewers
                ->map(fn ($r): array => [
                    'user_id' => (string) $r->user_id,
                    'stage' => (int) $r->stage,
                    'status' => (string) ($r->status ?? 'pending'),
                ])
                ->values()
                ->all();
            $documentReviewersSnapshot = $template->documentReviewers
                ->map(fn ($r): array => [
                    'user_id' => (string) $r->user_id,
                ])
                ->values()
                ->all();

            $next = $this->templateVersionRepository->nextVersionNumber($templateId);
            $trimmedChangelog = is_string($changelog) ? trim($changelog) : '';

            // changelog === null indica publicación automática (sin revisores o aprobación unánime).
            // En ese caso se usa un texto por defecto según si es la primera versión o una sucesiva.
            // Solo el flujo explícito (POST /publish) exige changelog, y esa validación la aplica
            // PublishTemplateRequest antes de llegar aquí.
            if ($trimmedChangelog === '') {
                $resolvedChangelog = $next === 1 ? 'Versión inicial' : 'Publicación automática';
            } else {
                $resolvedChangelog = $trimmedChangelog;
            }

            $createdVersion = $this->templateVersionRepository->createSnapshot(
                $templateId,
                $next,
                $blocksSnapshot,
                $resolvedChangelog,
                $actorId,
            );

            $this->templateVersionBlockLayerWriter->syncLayersForNewPublication($createdVersion, $template);

            $oldStatus = $template->status;
            $updated = $this->templateRepository->update($template, [
                'status' => 'published',
                'version' => $next,
            ]);

            $this->entityVersionLifecycleService->createPublishedSnapshotVersion(
                Template::class,
                (string) $template->id,
                $next,
                [
                    'template' => [
                        'id' => $template->id,
                        'process_id' => $template->process_id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'visibility_level' => $template->visibility_level,
                        'study_type_id' => $template->study_type_id,
                        'study_id' => $template->study_id,
                        'module_id' => $template->module_id,
                        'team_id' => $template->team_id,
                        'status' => 'published',
                        'version' => $next,
                    ],
                    'blocks' => $blocksSnapshot,
                    'reviewers' => [
                        'template_reviewers' => $templateReviewersSnapshot,
                        'document_reviewers' => $documentReviewersSnapshot,
                    ],
                ],
                $actorId,
                $resolvedChangelog,
            );

            event(new TemplateStateChanged(
                template: $updated,
                oldStatus: $oldStatus,
                newStatus: 'published',
                actorId: $actorId,
            ));

            return $updated;
        });
    }
}
