<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Templates\TemplateBlockPayloadDto;
use App\Enums\BlockType;
use App\Events\TemplateStateChanged;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Support\TemplateHeadSnapshot;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Events\BroadcastNotificationCreated;
use Maya\Messaging\Publishers\NotificationPublisher;

class TemplatePublishingService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly EntityVersionLifecycleServiceInterface $entityVersionLifecycleService,
        private readonly TemplateVersionBlockLayerWriter $templateVersionBlockLayerWriter,
        private readonly UserFavoriteRepositoryInterface $userFavoriteRepository,
        private readonly NotificationPublisher $notificationPublisher,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
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
        ?int $reviewerStage = null,
        ?string $reviewerName = null,
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
            reviewerStage: $reviewerStage,
            reviewerName: $reviewerName,
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

            $this->assertSequentialReviewerMayPublish($template, $reviewer);

            $template->load([
                'blocks' => fn ($q) => $q->orderBy('sort_order'),
                'reviewers' => fn ($q) => $q->orderBy('stage')->orderBy('user_id'),
                'documentReviewers' => fn ($q) => $q->orderBy('stage')->orderBy('user_id'),
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
            // Bloques estructurales sin cuerpo (hoja en blanco) exentos de "no vacío".
            $requiresContent = fn ($b) => ! ($b->block_type instanceof BlockType)
                || $b->block_type->requiresBodyContent();

            $emptyEditableBlock = $template->blocks->first(
                fn ($b) => in_array((string) $b->block_state, ['editable', 'modifiable'], true)
                    && $isEmptyContent($b) && $requiresContent($b)
            );

            $emptyLockedBlock = $template->blocks->first(
                fn ($b) => (string) $b->block_state === 'locked' && $isEmptyContent($b) && $requiresContent($b)
            );
            if ($emptyLockedBlock !== null) {
                throw ValidationException::withMessages([
                    'blocks' => ['Los bloques bloqueados no pueden estar vacíos.'],
                ]);
            }

            $blocksSnapshot = $template->blocks
                ->map(fn ($b) => TemplateBlockPayloadDto::fromModel($b)->toArray())
                ->values()->all();
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
                    'stage' => (int) $r->stage,
                ])
                ->values()
                ->all();

            $next = $this->entityVersionRepository->nextVersionNumber(Template::class, $templateId);
            $template->loadMissing('headVersion');
            $resolvedChangelog = VersionSubmissionChangelog::requireNonEmpty(
                $changelog,
                $template->headVersion?->changelog,
            );
            $templateFields = data_get($template->headVersion?->snapshot_data, TemplateHeadSnapshot::JSON_TEMPLATE_KEY);
            $templateFields = is_array($templateFields) ? $templateFields : [];

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
                    'document_review_mode' => TemplateHeadSnapshot::resolveDocumentReviewMode($templateFields),
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

            $this->templateVersionBlockLayerWriter->syncLayersForNewPublication((string) $entityVersion->id, (string) $template->id);

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

            // Clean submission data from head version via repository
            $this->templateRepository->cleanHeadVersionSubmissionData($templateId);

            // Si la publicación la provoca la aprobación final de un revisor, adjuntamos
            // su etapa y nombre. En publicación directa del creador, $reviewer es null.
            event(new TemplateStateChanged(
                template: $updated,
                oldStatus: $oldStatus,
                newStatus: 'published',
                actorId: $actorId,
                reviewerStage: $reviewer !== null ? (int) $reviewer->stage : null,
                reviewerName: $reviewer !== null ? $this->userDirectoryRepository->findNameById($actorId) : null,
            ));

            $createdBy = is_string($updated->created_by) && $updated->created_by !== '' ? $updated->created_by : null;
            if ($createdBy !== null) {
                $createdByTitle = 'Plantilla publicada';
                $createdByBody = 'La plantilla "'.$updated->name.'" ha sido publicada correctamente';
                $createdByMetadata = ['template_id' => (string) $updated->id];

                try {
                    $this->notificationPublisher->send(
                        type: 'template.published',
                        recipientId: $createdBy,
                        title: $createdByTitle,
                        body: $createdByBody,
                        titleKey: 'notifications.template.published.title',
                        bodyKey: 'notifications.template.published.body',
                        params: ['template_id' => (string) $updated->id, 'template_name' => $updated->name],
                        severity: 'info',
                        channels: ['app'],
                        metadata: $createdByMetadata,
                    );
                } catch (\Throwable $e) {
                    Log::warning('notification.publish_failed', [
                        'error' => $e->getMessage(),
                        'type' => 'template.published',
                        'template_id' => (string) $updated->id,
                    ]);
                }

                try {
                    BroadcastNotificationCreated::dispatch(
                        recipientId: $createdBy,
                        app: 'maya-dms',
                        type: 'template.published',
                        title: $createdByTitle,
                        body: $createdByBody,
                        metadata: $createdByMetadata,
                    );
                } catch (\Throwable $e) {
                    Log::warning('broadcast.dispatch_failed', [
                        'error' => $e->getMessage(),
                        'type' => 'template.published',
                        'template_id' => (string) $updated->id,
                    ]);
                }
            }

            // Notificar a owners de documentos activos que usan esta plantilla
            $affectedOwnerIds = $this->documentRepository->ownerIdsByTemplate((string) $updated->id);
            foreach ($affectedOwnerIds as $ownerId) {
                $ownerTitle = 'Plantilla actualizada';
                $ownerBody = 'Una plantilla que afecta a tus documentos "'.$updated->name.'" ha sido actualizada';
                $ownerMetadata = ['template_id' => (string) $updated->id, 'version' => $next];

                try {
                    $this->notificationPublisher->send(
                        type: 'template.version.affects_my_document',
                        recipientId: $ownerId,
                        title: $ownerTitle,
                        body: $ownerBody,
                        titleKey: 'notifications.template.version.affects_my_document.title',
                        bodyKey: 'notifications.template.version.affects_my_document.body',
                        params: ['template_id' => (string) $updated->id, 'template_name' => $updated->name, 'version' => $next, 'document_id' => $ownerId],
                        severity: 'medium',
                        channels: ['app'],
                        metadata: $ownerMetadata,
                    );
                } catch (\Throwable $e) {
                    Log::warning('notification.publish_failed', [
                        'error' => $e->getMessage(),
                        'type' => 'template.version.affects_my_document',
                        'template_id' => (string) $updated->id,
                        'owner_id' => $ownerId,
                    ]);
                }

                try {
                    BroadcastNotificationCreated::dispatch(
                        recipientId: $ownerId,
                        app: 'maya-dms',
                        type: 'template.version.affects_my_document',
                        title: $ownerTitle,
                        body: $ownerBody,
                        metadata: $ownerMetadata,
                    );
                } catch (\Throwable $e) {
                    Log::warning('broadcast.dispatch_failed', [
                        'error' => $e->getMessage(),
                        'type' => 'template.version.affects_my_document',
                        'template_id' => (string) $updated->id,
                        'owner_id' => $ownerId,
                    ]);
                }
            }

            return $updated;
        });
    }

    /**
     * En revisión secuencial, solo publica el revisor de la etapa pendiente activa.
     */
    private function assertSequentialReviewerMayPublish(Template $template, ?TemplateReviewer $reviewer): void
    {
        if ($reviewer === null || $template->review_mode !== 'sequential') {
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
}
