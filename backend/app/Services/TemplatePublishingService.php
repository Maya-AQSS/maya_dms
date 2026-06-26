<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\DTOs\Templates\TemplateDto;
use App\Enums\BlockType;
use App\Events\TemplateStateChanged;
use App\Models\Template;
use App\Models\TemplateReviewer;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateReviewerRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use App\Services\Concerns\NotifiesOwner;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Support\TemplateHeadSnapshot;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\NotificationPublisher;

class TemplatePublishingService
{
    use NotifiesOwner;

    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateReviewerRepositoryInterface $templateReviewerRepository,
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
     *
     * Devuelve TemplateDto; la presentación derivada se adjunta sobre el Model con
     * el callback `$beforeMap` antes de la conversión.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        $published = $this->templateRepository->transaction(function () use ($templateId, $changelog, $actorId) {
            $template = $this->templateRepository->findOrFailForUpdate($templateId);

            if (! in_array($template->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('validation.template_publish.state')],
                ]);
            }

            $reviewer = $this->templateReviewerRepository->findReviewerForTemplate((string) $template->getKey(), $actorId);

            $this->assertSequentialReviewerMayPublish($template, $reviewer);

            $this->templateReviewerRepository->loadRelationsForSnapshot($template);

            if ($template->blocks->isEmpty()) {
                throw ValidationException::withMessages([
                    'blocks' => [__('validation.template_publish.min_blocks')],
                ]);
            }

            $hasEditableBlock = $template->blocks->contains(
                fn ($b) => in_array((string) $b->block_state, ['editable', 'modifiable'], true)
            );
            if (! $hasEditableBlock) {
                throw ValidationException::withMessages([
                    'blocks' => [__('validation.template_publish.editable_block')],
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
                    'blocks' => [__('validation.template_publish.locked_not_empty')],
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
            $this->templateRepository->loadHeadVersion($template);
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
                    'document_delivery_deadline' => TemplateHeadSnapshot::normalizeDeadlineForSnapshot($template->document_delivery_deadline),
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

            $this->templateVersionBlockLayerWriter->syncLayersForNewPublication($entityVersion->id, (string) $template->id);

            // Migrar favoritos: los que apuntaban a la versión publicada anterior pasan a la nueva.
            if ($entityVersion->baseVersionId !== null) {
                $this->userFavoriteRepository->migrateFavoriteTemplateVersion(
                    $entityVersion->baseVersionId,
                    $entityVersion->id,
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
                $this->notifyOwner(
                    recipientId: $createdBy,
                    type: 'template.published',
                    title: 'Plantilla publicada',
                    body: 'La plantilla "'.$updated->name.'" ha sido publicada correctamente',
                    titleKey: 'notifications.template.published.title',
                    bodyKey: 'notifications.template.published.body',
                    params: ['template_id' => (string) $updated->id, 'template_name' => $updated->name],
                    severity: 'info',
                    metadata: ['template_id' => (string) $updated->id],
                );
            }

            // Notificar a owners de documentos activos que usan esta plantilla
            $affectedOwnerIds = $this->documentRepository->ownerIdsByTemplate((string) $updated->id);
            foreach ($affectedOwnerIds as $ownerId) {
                $this->notifyOwner(
                    recipientId: $ownerId,
                    type: 'template.version.affects_my_document',
                    title: 'Plantilla actualizada',
                    body: 'Una plantilla que afecta a tus documentos "'.$updated->name.'" ha sido actualizada',
                    titleKey: 'notifications.template.version.affects_my_document.title',
                    bodyKey: 'notifications.template.version.affects_my_document.body',
                    params: ['template_id' => (string) $updated->id, 'template_name' => $updated->name, 'version' => $next, 'document_id' => $ownerId],
                    severity: 'medium',
                    metadata: ['template_id' => (string) $updated->id, 'version' => $next],
                );
            }

            return $updated;
        });

        if ($beforeMap !== null) {
            $beforeMap($published);
        }

        return TemplateDto::fromModel($published);
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
                'stage' => [__('validation.template_review.sequential_order')],
            ]);
        }
    }
}
