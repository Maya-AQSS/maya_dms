<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\TemplateStateChanged;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\NotificationPublisher;

class TemplatePublishingService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly EntityVersionLifecycleServiceInterface $entityVersionLifecycleService,
        private readonly TemplateVersionBlockLayerWriter $templateVersionBlockLayerWriter,
        private readonly UserFavoriteRepositoryInterface $userFavoriteRepository,
        private readonly NotificationPublisher $notificationPublisher,
    ) {}

    /**
     * Actualiza estado (y opcionalmente metadatos delegados del cabezal) y emite TemplateStateChanged.
     *
     * @param  array<string, mixed>  $extraHeadAttributes
     */
    public function transitionStatus(
        Template $template,
        string $newStatus,
        string $actorId,
        array $extraHeadAttributes = [],
    ): Template {
        $oldStatus = $template->status;
        $updated = $this->templateRepository->update(
            $template,
            array_merge(['status' => $newStatus], $extraHeadAttributes),
        );

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

            $emptyEditableBlock = $template->blocks->first(
                fn ($b) => in_array((string) $b->block_state, ['editable', 'modifiable'], true)
                    && $isEmptyContent($b)
            );

            $emptyLockedBlock = $template->blocks->first(
                fn ($b) => (string) $b->block_state === 'locked' && $isEmptyContent($b)
            );
            if ($emptyLockedBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques bloqueados no pueden estar vacíos.'],
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

            $next = $this->entityVersionRepository->nextVersionNumber(Template::class, $templateId);
            $trimmedChangelog = is_string($changelog) ? trim($changelog) : '';

            // Sin changelog explícito (tras trim): texto por defecto en la fila publicada en entity_versions.
            // El número de versión ($next) solo vive en entity_versions y en este snapshot, no en la tabla templates.
            // El flujo POST /publish puede exigir changelog en republicaciones vía PublishTemplateRequest.
            if ($trimmedChangelog === '') {
                $resolvedChangelog = 'Publicación automática';
            } else {
                $resolvedChangelog = $trimmedChangelog;
            }

            $snapshotPayload = [
                'template' => [
                    'id' => $template->id,
                    'created_by' => $template->created_by,
                    'process_id' => $template->process_id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'visibility_level' => TemplateHeadSnapshot::normalizeVisibilityForSnapshot($template->visibility_level),
                    'delivery_deadline' => TemplateHeadSnapshot::normalizeDeadlineForSnapshot($template->delivery_deadline),
                    'study_type_id' => $template->study_type_id,
                    'study_id' => $template->study_id,
                    'module_id' => $template->module_id,
                    'team_id' => $template->team_id,
                    'review_stages' => (int) $template->review_stages,
                    'review_mode' => (string) $template->review_mode,
                    'status' => 'published',
                    'version' => $next,
                ],
                'blocks' => $blocksSnapshot,
                'reviewers' => [
                    'template_reviewers' => $templateReviewersSnapshot,
                    'document_reviewers' => $documentReviewersSnapshot,
                ],
            ];

            $entityVersion = $this->entityVersionLifecycleService->createPublishedSnapshotVersion(
                Template::class,
                (string) $template->id,
                $next,
                $snapshotPayload,
                $actorId,
                $resolvedChangelog,
            );

            $this->templateVersionBlockLayerWriter->syncLayersForNewPublication($entityVersion, $template);

            // Migrar favoritos: los que apuntaban a la versión publicada anterior pasan a la nueva.
            if ($entityVersion->base_version_id !== null) {
                $this->userFavoriteRepository->migrateFavoriteTemplateVersion(
                    (string) $entityVersion->base_version_id,
                    (string) $entityVersion->id,
                );
            }

            $oldStatus = $template->status;
            $updated = $this->templateRepository->update($template, [
                'status' => 'published',
            ]);

            $updated->loadMissing('headVersion');
            $headEv = $updated->headVersion;
            if ($headEv !== null) {
                $headData = is_array($headEv->snapshot_data) ? $headEv->snapshot_data : [];
                unset($headData['blocks_at_submission'], $headData['blocks_at_previous_submission'], $headData['blocks_submission_history']);
                $headEv->snapshot_data = $headData ?: null;
                $headEv->save();
            }

            event(new TemplateStateChanged(
                template: $updated,
                oldStatus: $oldStatus,
                newStatus: 'published',
                actorId: $actorId,
            ));

            $createdBy = is_string($updated->created_by) && $updated->created_by !== '' ? $updated->created_by : null;
            if ($createdBy !== null) {
                try {
                    $this->notificationPublisher->send(
                        type: 'template.published',
                        recipientId: $createdBy,
                        title: 'Plantilla publicada',
                        body: 'La plantilla "' . $updated->name . '" ha sido publicada correctamente',
                        channels: ['app'],
                        metadata: ['template_id' => (string) $updated->id],
                    );
                } catch (\Throwable $e) {
                    Log::warning('notification.publish_failed', [
                        'error' => $e->getMessage(),
                        'type' => 'template.published',
                        'template_id' => (string) $updated->id,
                    ]);
                }
            }

            return $updated;
        });
    }
}
