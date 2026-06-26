<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\SyncUsersDto;
use App\DTOs\Templates\TemplateDto;
use App\DTOs\Templates\TemplateFilterDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\DTOs\Versioning\EntityVersionDto;
use App\DTOs\Versioning\TemplateVersionDetailDto;
use App\DTOs\Versioning\TemplateVersionSummaryDto;
use App\DTOs\Versioning\WorkingRevisionConflictDto;
use App\Enums\TemplateVisibilityLevel;
use App\Events\OwnershipTransferred;
use App\Models\EntityVersion;
use App\Models\JwtUser;
use App\Models\Template;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateReviewerRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Concerns\NotifiesOwner;
use App\Services\Contracts\TemplateServiceInterface;
use App\Support\AcademicScopeContext;
use App\Support\AcademicScopeNormalizer;
use App\Support\TemplateHeadSnapshot;
use App\Support\TemplateVersionSnapshotParser;
use App\Support\WorkingRevisionConflictResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maya\Http\Pagination\PaginatedDto;
use Maya\Messaging\Publishers\NotificationPublisher;
use RuntimeException;

class TemplateService implements TemplateServiceInterface
{
    use NotifiesOwner;

    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateBlockRepositoryInterface $templateBlockRepository,
        private readonly TemplateReviewerRepositoryInterface $templateReviewerRepository,
        private readonly TemplatePublishingService $templatePublishingService,
        private readonly TemplateReviewService $templateReviewService,
        private readonly TemplateReviewerAssignmentService $templateReviewerAssignmentService,
        private readonly DocumentBlockRepositoryInterface $documentBlockRepository,
        private readonly AcademicHierarchyRepositoryInterface $academicHierarchyRepository,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
        private readonly EntityVersionDestroyService $entityVersionDestroyService,
        private readonly TemplateVersionBlockLayerResolver $templateVersionBlockLayerResolver,
        private readonly NotificationPublisher $notificationPublisher,
    ) {}

    /** @var array<string, ?string> */
    private array $userNameCache = [];

    /**
     * Canónico: devuelve el DTO de la plantilla.
     */
    public function findOrFail(string $id): TemplateDto
    {
        return TemplateDto::fromModel($this->templateRepository->findOrFail($id));
    }

    /**
     * Aplica el callback de presentación (adjunta atributos derivados sobre el
     * Model: can_clone, team embebido, …) y convierte a DTO. Patrón espejo del
     * `$beforeMap` de {@see self::paginateFiltered}.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    private function toDto(Template $template, ?callable $beforeMap = null): TemplateDto
    {
        if ($beforeMap !== null) {
            $beforeMap($template);
        }

        return TemplateDto::fromModel($template);
    }

    /**
     * Variante de uso interno: devuelve el Model. Necesario para attachs
     * (`can_clone`, `review_mode`, etc.), policies y encadenado con otros
     * métodos del Service que reciben Model.
     */
    public function findModelOrFail(string $id): Template
    {
        return $this->templateRepository->findOrFail($id);
    }

    /**
     * Carga múltiples plantillas por sus IDs aplicando el global scope de visibilidad.
     * El resultado está indexado por ID.
     *
     * @param  list<string>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<string, Template>
     */
    public function findManyByIds(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return $this->templateRepository->findManyByIds($ids);
    }

    /**
     * Localiza una plantilla por su ID sin el global scope de catálogo `user_access`.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template
    {
        return $this->templateRepository->findOrFailWithoutCatalogScope($id);
    }

    /**
     * Alias canónico de {@see findOrFailWithoutCatalogScope} — nombre homogéneo
     * con DocumentServiceInterface::findModelOrFailWithoutUserAccess.
     */
    public function findModelOrFailWithoutUserAccess(string $id): Template
    {
        return $this->findOrFailWithoutCatalogScope($id);
    }

    /**
     * Localiza una versión de plantilla por su ID.
     */
    public function findVersionOrFail(string $versionId): EntityVersionDto
    {
        return EntityVersionDto::fromModel($this->templateVersionRepository->findOrFail($versionId));
    }

    /**
     * Localiza una versión polimórfica por su ID.
     */
    public function findEntityVersionOrFail(string $versionId): EntityVersionDto
    {
        return EntityVersionDto::fromModel($this->entityVersionRepository->findOrFail($versionId));
    }

    /**
     * Envía el borrador a revisión. Solo el creador de la plantilla puede ejecutar esta acción.
     *
     * - Sin revisores asignados → publica automáticamente.
     * - Con revisores → resetea sus estados a `pending` (necesario para rondas
     *   sucesivas: en draft post-rechazo los estados quedan visibles para el autor,
     *   y solo se limpian al reenviar) y transiciona a `in_review`.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function submitForReview(string $templateId, string $actorId, string $changelog, ?callable $beforeMap = null): TemplateDto
    {
        return $this->templateReviewService->submitForReview($templateId, $actorId, $changelog, $beforeMap);
    }

    /**
     * Rechaza la revisión de la plantilla.
     *
     * Registra el rechazo del actor en `template_reviewers` (auditoría de quién rechazó)
     * y transiciona la plantilla a borrador. Los estados quedan visibles en draft
     * para que el autor sepa quién rechazó; se limpian al reenviar.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function rejectReview(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        return $this->templateReviewService->rejectReview($templateId, $actorId, $beforeMap);
    }

    /**
     * Registra la aprobación del revisor activo.
     *
     * En modo secuencial exige que todos los stages anteriores estén aprobados.
     * Si tras esta aprobación todos los revisores están en `approved`, publica
     * la plantilla automáticamente con un snapshot.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function approveReview(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        return $this->templateReviewService->approveReview($templateId, $actorId, $beforeMap);
    }

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        return $this->templatePublishingService->publishWithSnapshot($templateId, $changelog, $actorId, $beforeMap);
    }

    /**
     * Detalle de una versión publicada de plantilla con el snapshot de bloques
     * reconstruido y los nombres de autor/revisores ya resueltos. El Resource
     * solo mapea; toda la resolución (BD + capas) vive aquí, en el Service.
     */
    public function findTemplateVersionDetailOrFail(string $versionId): TemplateVersionDetailDto
    {
        $version = $this->templateVersionRepository->findOrFail($versionId);

        $blocksSnapshot = $this->templateVersionBlockLayerResolver->resolveBlocksSnapshot((string) $version->id);

        $snapshotData = is_array($version->snapshot_data) ? $version->snapshot_data : [];
        $templateSnapshot = isset($snapshotData['template']) && is_array($snapshotData['template'])
            ? $snapshotData['template']
            : null;

        $authorId = TemplateVersionSnapshotParser::authorId($snapshotData)
            ?? $this->firstNonEmptyString($version->created_by);
        $publishedBy = $this->firstNonEmptyString($version->published_by);

        return new TemplateVersionDetailDto(
            id: (string) $version->id,
            templateId: (string) $version->versionable_id,
            versionNumber: (int) $version->version_number,
            templateSnapshot: $templateSnapshot,
            blocksSnapshot: $blocksSnapshot,
            changelog: $version->changelog !== null ? (string) $version->changelog : null,
            publishedBy: $publishedBy,
            publishedByName: $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
            authorName: $authorId !== null ? $this->resolveUserNameById($authorId) : null,
            reviewerNames: $this->resolveReviewerNames($snapshotData),
            publishedAt: $version->published_at?->toIso8601String(),
            createdAt: $version->created_at?->toIso8601String(),
            updatedAt: $version->updated_at?->toIso8601String(),
        );
    }

    /**
     * Historial de versiones publicadas (metadatos, sin el JSONB de bloques) con
     * los nombres de autor/revisores ya resueltos. El Resource solo mapea.
     *
     * @return list<TemplateVersionSummaryDto>
     */
    public function listPublishedVersionSummaries(string $templateId): array
    {
        return $this->entityVersionRepository->listPublishedForEntityOrdered(Template::class, $templateId)
            ->map(function (EntityVersion $v): TemplateVersionSummaryDto {
                $snapshotData = is_array($v->snapshot_data) ? $v->snapshot_data : [];
                $authorId = TemplateVersionSnapshotParser::authorId($snapshotData)
                    ?? $this->firstNonEmptyString($v->created_by);
                $publishedBy = $this->firstNonEmptyString($v->published_by);

                return new TemplateVersionSummaryDto(
                    id: (string) $v->id,
                    templateId: (string) $v->versionable_id,
                    versionNumber: (int) $v->version_number,
                    publishedAt: $v->published_at?->toIso8601String(),
                    publishedBy: $publishedBy,
                    publishedByName: $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                    authorName: $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                    reviewerNames: $this->resolveReviewerNames($snapshotData),
                    changelog: $v->changelog !== null ? (string) $v->changelog : null,
                );
            })
            ->values()
            ->all();
    }

    /**
     * Resuelve los nombres de los revisores de plantilla a partir del snapshot.
     *
     * @param  array<string, mixed>  $snapshotData
     * @return list<string>
     */
    private function resolveReviewerNames(array $snapshotData): array
    {
        $names = [];
        foreach (TemplateVersionSnapshotParser::reviewerIds($snapshotData) as $uid) {
            $name = $this->resolveUserNameById($uid);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Devuelve el primer argumento que sea un string no vacío, o null.
     */
    private function firstNonEmptyString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resuelve el nombre de un usuario por su ID con caché por proceso.
     */
    private function resolveUserNameById(string $userId): ?string
    {
        if (array_key_exists($userId, $this->userNameCache)) {
            return $this->userNameCache[$userId];
        }

        return $this->userNameCache[$userId] = $this->userDirectoryRepository->findNameById($userId);
    }

    /**
     * Listado paginado de plantillas con filtros de dominio (ADR-C).
     *
     * Enriquece los modelos de la página actual antes de mapear a DTO (p. ej.
     * `latest_published_*`). El controller puede pasar un callback para presentación
     * (`can_clone`, `team`, …) antes del mapeo.
     *
     * @param  callable(Collection<int, Template>): void|null  $beforeMap
     * @return PaginatedDto<TemplateDto>
     */
    public function paginateFiltered(
        TemplateFilterDto $filter,
        string $viewerId,
        ?callable $beforeMap = null,
    ): PaginatedDto {
        $paginator = $this->templateRepository->paginateFiltered($filter);
        /** @var Collection<int, Template> $templates */
        $templates = collect($paginator->items());

        if ($templates->isNotEmpty()) {
            $this->attachLatestPublishedVersionMeta($templates);
            $this->overlayPublishedSnapshotForNonOwners($templates, $viewerId);
            if ($beforeMap !== null) {
                $beforeMap($templates);
            }
        }

        return PaginatedDto::fromPaginator(
            $paginator,
            static fn (Template $template) => TemplateDto::fromModel($template),
        );
    }

    /**
     * Listado con filtros (sin paginación en servidor; el front pagina en cliente).
     * Enriquece cada plantilla con metadatos de la última versión publicada para el API.
     *
     * @return Collection<int, TemplateDto>
     */
    public function listFiltered(FilterTemplatesDto $filters): Collection
    {
        $templates = $this->templateRepository->listFiltered($filters);
        $this->templateRepository->attachLatestPublishedVersionMeta($templates);

        return $templates->map(static fn (Template $t) => TemplateDto::fromModel($t));
    }

    public function attachLatestPublishedVersionMeta(Collection $templates): void
    {
        $this->templateRepository->attachLatestPublishedVersionMeta($templates);
    }

    public function resolveWorkingRevisionConflict(Template $template): WorkingRevisionConflictDto
    {
        $this->templateRepository->loadHeadVersion($template);
        $realHead = $template->headVersion;
        $editorId = (string) (
            data_get($realHead?->snapshot_data, TemplateHeadSnapshot::JSON_TEMPLATE_KEY.'.created_by')
            ?? $realHead?->created_by
            ?? ''
        );
        $editorName = $editorId !== ''
            ? ($this->templateRepository->getUserNameById($editorId) ?? null)
            : null;

        return WorkingRevisionConflictResolver::resolve(
            (string) $template->status,
            $this->findLatestPublishedVersion($template->id),
            $realHead,
            $editorName,
        );
    }

    public function attachWorkingRevisionPresentationMeta(Template $template): void
    {
        WorkingRevisionConflictResolver::attachToModel(
            $template,
            $this->resolveWorkingRevisionConflict($template),
        );
    }

    public function overlayPublishedSnapshotForNonOwners(Collection $templates, string $viewerId): void
    {
        // Only process draft/in_review/rejected templates that the viewer doesn't own.
        $candidates = $templates->filter(
            fn (Template $t) => in_array($t->status, ['draft', 'in_review', 'rejected'], true)
                && (string) $t->created_by !== $viewerId,
        );

        if ($candidates->isEmpty()) {
            return;
        }

        $candidateIds = $candidates->map(fn (Template $t) => (string) $t->getKey())->all();

        // Exclude templates where viewer is an active reviewer (they see real content).
        $reviewerTemplateIds = $this->templateReviewerRepository->findTemplateIdsWithReviewer($candidateIds, $viewerId);

        $nonOwnerIds = array_values(array_filter(
            $candidateIds,
            fn (string $id) => ! isset($reviewerTemplateIds[$id]),
        ));

        if (empty($nonOwnerIds)) {
            return;
        }

        // Batch-load the latest published EntityVersion per template.
        $publishedByTemplate = $this->entityVersionRepository->findLatestPublishedEntityVersionsByVersionableIds(
            Template::class,
            $nonOwnerIds,
        );

        foreach ($candidates as $template) {
            $id = (string) $template->getKey();
            if (isset($reviewerTemplateIds[$id])) {
                continue;
            }
            $published = $publishedByTemplate->get($id);
            if ($published !== null) {
                $template->setRelation('headVersion', $published);
            }
        }
    }

    /**
     * Crea una plantilla con los atributos dados.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function create(CreateTemplateDto $dto, ?callable $beforeMap = null): TemplateDto
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('Cannot create template without authenticated user.');
        }
        $this->assertTemplateMetadataInvariants(
            $dto->name,
            $dto->deliveryDeadline,
            $dto->visibilityLevel,
            $dto->documentDeliveryDeadline,
        );

        return $this->toDto($this->templateRepository->create([
            'process_id' => $dto->processId,
            'name' => $dto->name,
            'description' => $dto->description,
            'visibility_level' => $dto->visibilityLevel,
            'delivery_deadline' => $dto->deliveryDeadline,
            'document_delivery_deadline' => $dto->documentDeliveryDeadline,
            'study_type_id' => $dto->studyTypeId,
            'study_id' => $dto->studyId,
            'module_id' => $dto->moduleId,
            'team_id' => $dto->teamId,
            'theme_id' => $dto->themeId,
            'created_by' => (string) $userId,
            'status' => 'draft',
            'review_stages' => $dto->reviewStages,
            'review_mode' => $dto->reviewMode,
            'document_review_mode' => $dto->documentReviewMode ?? $dto->reviewMode,
        ]), $beforeMap);
    }

    /**
     * Actualiza una plantilla con los atributos dados.
     * Recibe el modelo ya resuelto para evitar una query redundante.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function update(Template $template, UpdateTemplateDto $dto, ?callable $beforeMap = null): TemplateDto
    {
        $previousCreatedBy = (string) $template->created_by;
        $attributes = [];

        if ($dto->setName) {
            $attributes['name'] = $dto->name;
        }
        if ($dto->setDescription) {
            $attributes['description'] = $dto->description;
        }
        if ($dto->setVisibilityLevel) {
            $attributes['visibility_level'] = $dto->visibilityLevel;
        }
        if ($dto->setDeliveryDeadline) {
            $attributes['delivery_deadline'] = $dto->deliveryDeadline;
        }
        if ($dto->setDocumentDeliveryDeadline) {
            $attributes['document_delivery_deadline'] = $dto->documentDeliveryDeadline;
        }
        if ($dto->setStudyTypeId) {
            $attributes['study_type_id'] = $dto->studyTypeId;
        }
        if ($dto->setStudyId) {
            $attributes['study_id'] = $dto->studyId;
        }
        if ($dto->setModuleId) {
            $attributes['module_id'] = $dto->moduleId;
        }
        if ($dto->setTeamId) {
            $attributes['team_id'] = $dto->teamId;
        }
        if ($dto->setReviewStages) {
            $attributes['review_stages'] = $dto->reviewStages;
        }
        if ($dto->setReviewMode) {
            $attributes['review_mode'] = $dto->reviewMode;
        }
        if ($dto->setDocumentReviewMode) {
            $attributes['document_review_mode'] = $dto->documentReviewMode;
        }
        if ($dto->setThemeId) {
            $attributes['theme_id'] = $dto->themeId;
        }
        if ($dto->setCreatedBy && $dto->createdBy !== null) {
            $attributes['created_by'] = $dto->createdBy;
        }

        $ownershipTransferred = $dto->setCreatedBy
            && $dto->createdBy !== null
            && (string) $dto->createdBy !== $previousCreatedBy;

        if ($ownershipTransferred) {
            $visibility = $attributes['visibility_level'] ?? $template->visibility_level;
            $level = $visibility instanceof TemplateVisibilityLevel
                ? $visibility
                : TemplateVisibilityLevel::tryFrom((string) $visibility);

            // Solo las plantillas personales pueden arrastrar contexto académico del
            // titular anterior (restos que no aplican). El ámbito de visibilidad
            // study_type/study/module/team es propiedad de la plantilla, no del owner.
            if ($level === TemplateVisibilityLevel::Personal) {
                $attributes['study_type_id'] = null;
                $attributes['study_id'] = null;
                $attributes['module_id'] = null;
                $attributes['team_id'] = null;
            }
        }

        $this->assertTemplateMetadataInvariants(
            (string) ($attributes['name'] ?? $template->name),
            $attributes['delivery_deadline'] ?? $template->delivery_deadline,
            $attributes['visibility_level'] ?? $template->visibility_level,
            $attributes['document_delivery_deadline'] ?? $template->document_delivery_deadline,
        );

        $updated = $this->templateRepository->update($template, $attributes);

        if ($ownershipTransferred) {
            $request = request();
            $actorId = (string) (Auth::id() ?? '');
            $newOwnerId = (string) $dto->createdBy;
            // DMS-B11: nombres (actor / propietario anterior / nuevo) en una sola consulta.
            $names = $this->userDirectoryRepository->findNamesByIds([
                $actorId,
                $previousCreatedBy,
                $newOwnerId,
            ]);
            $actorName = $names[$actorId] ?? '';

            OwnershipTransferred::dispatch(
                'template',
                (string) $updated->getKey(),
                $previousCreatedBy,
                $newOwnerId,
                $actorId,
                $names[$previousCreatedBy] ?? null,
                $names[$newOwnerId] ?? null,
                $request?->ip(),
                $request?->userAgent(),
            );

            $this->notifyOwner(
                recipientId: $newOwnerId,
                type: 'template.ownership_transferred',
                title: __('notifications.template.ownership_transferred.title'),
                body: __('notifications.template.ownership_transferred.body', [
                    'actor_name' => $actorName,
                    'template_name' => $updated->name,
                ]),
                titleKey: 'notifications.template.ownership_transferred.title',
                bodyKey: 'notifications.template.ownership_transferred.body',
                params: [
                    'template_id' => (string) $updated->getKey(),
                    'template_name' => $updated->name,
                    'actor_name' => $actorName,
                ],
                severity: 'info',
                metadata: ['template_id' => (string) $updated->getKey()],
            );
        }

        return $this->toDto($updated, $beforeMap);
    }

    /**
     * Elimina una plantilla de forma recuperable.
     *
     * - Con documentos asociados: transiciona a `archived` (soft delete semántico;
     *   los documentos siguen vivos). Se puede recuperar cambiando el estado.
     * - Sin documentos: soft delete con deleted_at. Se puede recuperar con restore.
     *
     * @return bool true si se eliminó por soft delete (sin documentos); false si se archivó (con documentos).
     */
    public function destroy(string $templateId, string $actorId): bool
    {
        $template = $this->templateRepository->findOrFail($templateId);

        $latestPublished = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
        if ($latestPublished !== null) {
            $this->templateRepository->loadHeadVersion($template);
            $head = $template->headVersion;

            if ($head !== null && (int) $head->version_number === 0 && in_array((string) $head->status, ['draft', 'in_review'], true)) {
                // Published version exists + working draft → discard draft, restore published.
                $this->destroyVersion($templateId, (string) $head->id, $actorId);

                return false;
            }

            throw ValidationException::withMessages([
                'template' => [__('validation.template.delete_published')],
            ]);
        }

        if ($this->templateRepository->templateHasDocuments($templateId)) {
            if ($template->status !== 'archived') {
                $this->templatePublishingService->transitionStatus($template, 'archived', $actorId);
            }

            return false;
        }

        $this->templateRepository->delete($template);

        return true;
    }

    /**
     * Clona una plantilla origen hacia una nueva en borrador.
     *
     * Si existe versión publicada en {@see EntityVersion}, la copia se materializa desde ese
     * snapshot; si no, desde bloques y revisores vivos.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function clone(string $sourceTemplateId, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        $source = $this->templateRepository->findOrFail($sourceTemplateId);
        $this->assertTemplateMetadataInvariants(
            (string) $source->name,
            $source->delivery_deadline,
            $source->visibility_level,
            $source->document_delivery_deadline,
        );

        $published = $this->resolveLatestPublishedTemplateSnapshotForClone((string) $source->id);
        $copy = $published !== null
            ? $this->cloneTemplateFromPublishedSnapshot($source, $published, $actorId)
            : $this->cloneTemplateFromLiveSource($source, $actorId);

        return $this->toDto($copy, $beforeMap);
    }

    /**
     * Transición explícita publicada → borrador para preparar la siguiente versión publicada.
     */
    public function hasPublishedSnapshot(string $templateId): bool
    {
        return $this->entityVersionRepository->findLatestPublishedMetaForVersionable(Template::class, $templateId) !== null;
    }

    public function findLatestPublishedVersion(string $templateId): ?EntityVersion
    {
        return $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
    }

    /**
     * @param  callable(Template): void|null  $beforeMap
     */
    public function startNewRevisionCycle(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        $template = $this->templateRepository->findOrFail($templateId);

        if ($template->status !== 'published') {
            throw ValidationException::withMessages([
                'status' => [__('validation.template.new_version_state')],
            ]);
        }

        return $this->toDto($this->templatePublishingService->transitionStatus(
            $template,
            'draft',
            $actorId,
            ['created_by' => $actorId],
        ), $beforeMap);
    }

    /**
     * Descarta la versión de trabajo actual (head mutable) y restaura snapshot/revisores de la última publicación.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function destroyVersion(string $templateId, string $versionId, string $actorId, ?callable $beforeMap = null): TemplateDto
    {
        $restored = $this->templateRepository->transaction(function () use ($templateId, $versionId) {
            $template = $this->templateRepository->findOrFail($templateId);
            $this->templateRepository->loadHeadVersion($template);
            $head = $template->headVersion;

            // Shared guards + entity_version reset (delegated to EntityVersionDestroyService).
            $publishedSnapshot = $this->entityVersionDestroyService->assertAndResetToPublished(
                entityClass: Template::class,
                entityId: $templateId,
                targetVersionId: $versionId,
                head: $head,
                notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual de la plantilla.',
                statusMessage: 'Solo se pueden descartar versiones no publicadas (draft/in_review/rejected).',
                noPublishedMessage: 'No existe una versión publicada a la que restaurar.',
            );

            $publishedTemplate = isset($publishedSnapshot['template']) && is_array($publishedSnapshot['template'])
                ? $publishedSnapshot['template']
                : [];

            // Domain-specific restoration (blocks + reviewers) remains here.
            $this->restoreTemplateBlocksFromPublishedSnapshot($template, $publishedSnapshot);
            $this->restoreReviewersFromPublishedSnapshotIfPresent($template, $publishedSnapshot);

            // Refresca campos delegados críticos para consistencia con el snapshot restaurado.
            $currentCreatorId = (string) $template->created_by;
            $template = $this->templateRepository->update($template, [
                'status' => 'published',
                'created_by' => isset($publishedTemplate['created_by']) && is_string($publishedTemplate['created_by'])
                    ? $publishedTemplate['created_by']
                    : $currentCreatorId,
            ]);

            $this->templateRepository->loadReviewers($template, withDocumentReviewers: true);

            return $template;
        });

        return $this->toDto($restored, $beforeMap);
    }

    /**
     * @param  array<string, mixed>  $publishedSnapshot
     */
    private function restoreTemplateBlocksFromPublishedSnapshot(Template $template, array $publishedSnapshot): void
    {
        $blocks = isset($publishedSnapshot['blocks']) && is_array($publishedSnapshot['blocks'])
            ? $publishedSnapshot['blocks']
            : [];
        $templateId = (string) $template->getKey();
        $publishedBlockIds = [];

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $snapshotId = isset($block['id']) && is_string($block['id']) && $block['id'] !== ''
                ? $block['id']
                : null;
            $blockId = $snapshotId ?? (string) Str::uuid();
            $publishedBlockIds[] = $blockId;

            $rawTitle = $block['title'] ?? null;
            $title = match (true) {
                $rawTitle === null => null,
                is_string($rawTitle) => $rawTitle,
                is_scalar($rawTitle) => (string) $rawTitle,
                default => null,
            };

            $values = [
                'template_id' => $templateId,
                'title' => $title,
                'description' => array_key_exists('description', $block) ? $block['description'] : null,
                'default_content' => array_key_exists('default_content', $block) ? $block['default_content'] : null,
                'block_state' => isset($block['block_state']) && is_string($block['block_state'])
                    ? $block['block_state']
                    : 'editable',
                'sort_order' => isset($block['sort_order']) ? (int) $block['sort_order'] : (int) $index,
            ];

            $this->templateBlockRepository->upsertByIdForTemplate($blockId, $values);
        }

        $blockIdsInUseByDocuments = $this->documentBlockRepository
            ->findTemplateBlockIdsInUseByTemplate($templateId);

        $protectedIds = array_values(array_unique(array_merge($publishedBlockIds, $blockIdsInUseByDocuments)));

        $this->templateBlockRepository->deleteForTemplateExcept($templateId, $protectedIds);
    }

    /**
     * @param  array<string, mixed>  $publishedSnapshot
     */
    private function restoreReviewersFromPublishedSnapshotIfPresent(Template $template, array $publishedSnapshot): void
    {
        if (! array_key_exists('reviewers', $publishedSnapshot) || ! is_array($publishedSnapshot['reviewers'])) {
            return;
        }

        $reviewersSection = $publishedSnapshot['reviewers'];
        $templateReviewers = isset($reviewersSection['template_reviewers']) && is_array($reviewersSection['template_reviewers'])
            ? $reviewersSection['template_reviewers']
            : [];
        $documentReviewers = isset($reviewersSection['document_reviewers']) && is_array($reviewersSection['document_reviewers'])
            ? $reviewersSection['document_reviewers']
            : [];

        $templateId = (string) $template->getKey();

        $filteredTemplateReviewers = [];
        foreach ($templateReviewers as $row) {
            if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                continue;
            }
            $filteredTemplateReviewers[] = [
                'user_id' => $row['user_id'],
                'stage' => isset($row['stage']) ? (int) $row['stage'] : 1,
                'status' => 'pending',
            ];
        }
        $this->templateReviewerRepository->replaceTemplateReviewers($templateId, $filteredTemplateReviewers);

        $filteredDocumentReviewers = [];
        foreach ($documentReviewers as $index => $row) {
            if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                continue;
            }
            $filteredDocumentReviewers[] = [
                'user_id' => $row['user_id'],
                'stage' => isset($row['stage']) && (int) $row['stage'] > 0
                    ? (int) $row['stage']
                    : $index + 1,
            ];
        }
        $this->templateReviewerRepository->replaceDocumentReviewers($templateId, $filteredDocumentReviewers);
    }

    /**
     * @param  array{
     *     kind: 'entity'|'legacy',
     *     template_meta: array<string, mixed>,
     *     blocks: array<int, array<string, mixed>>,
     *     reviewers_from_snapshot: bool,
     *     template_reviewers: list<array<string, mixed>>,
     *     document_reviewers: list<array<string, mixed>>
     * }  $published
     */
    private function cloneTemplateFromPublishedSnapshot(Template $source, array $published, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($source, $published, $actorId) {
            $kind = $published['kind'];
            $templateMeta = $published['template_meta'];

            $nameBase = $this->cloneTemplateNameBase($kind, $templateMeta, $source);
            $cloneVisibility = $this->normalizeTemplateVisibilityLevelForClone($kind, $templateMeta, $source);
            $cloneDeliveryDeadline = $this->cloneTemplateDeliveryDeadline($kind, $templateMeta, $source);
            $cloneDocumentDeliveryDeadline = $this->cloneTemplateDocumentDeliveryDeadline($kind, $templateMeta, $source);
            $cloneName = $nameBase.' (copia)';
            $this->assertTemplateMetadataInvariants(
                $cloneName,
                $cloneDeliveryDeadline,
                $cloneVisibility,
                $cloneDocumentDeliveryDeadline,
            );

            $target = $this->templateRepository->create([
                'process_id' => $source->process_id,
                'name' => $cloneName,
                'description' => $this->cloneTemplateDescription($kind, $templateMeta, $source),
                'visibility_level' => $cloneVisibility,
                'delivery_deadline' => $cloneDeliveryDeadline,
                'document_delivery_deadline' => $cloneDocumentDeliveryDeadline,
                'study_type_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'study_type_id'),
                'study_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'study_id'),
                'module_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'module_id'),
                'team_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'team_id'),
                'created_by' => $actorId,
                'status' => 'draft',
                'review_stages' => $this->cloneTemplateReviewStages($kind, $templateMeta, $source),
                'review_mode' => $this->cloneTemplateReviewMode($kind, $templateMeta, $source),
                'document_review_mode' => $this->cloneTemplateDocumentReviewMode($kind, $templateMeta, $source),
            ]);

            $this->templateRepository->insertBlocksFromPublishedSnapshot((string) $target->getKey(), $published['blocks']);

            $targetId = (string) $target->getKey();
            if ($published['reviewers_from_snapshot'] ?? false) {
                $snapshotTemplateReviewers = [];
                foreach ($published['template_reviewers'] as $row) {
                    if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                        continue;
                    }
                    $snapshotTemplateReviewers[] = [
                        'user_id' => $row['user_id'],
                        'stage' => isset($row['stage']) ? (int) $row['stage'] : 1,
                        'status' => 'pending',
                    ];
                }
                $snapshotDocumentReviewers = [];
                foreach ($published['document_reviewers'] as $index => $row) {
                    if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                        continue;
                    }
                    $snapshotDocumentReviewers[] = [
                        'user_id' => $row['user_id'],
                        'stage' => isset($row['stage']) && (int) $row['stage'] > 0
                            ? (int) $row['stage']
                            : $index + 1,
                    ];
                }
                $this->templateReviewerRepository->replaceTemplateReviewers($targetId, $snapshotTemplateReviewers);
                $this->templateReviewerRepository->replaceDocumentReviewers($targetId, $snapshotDocumentReviewers);
            } else {
                $this->templateReviewerRepository->copyReviewersFromTemplate($source, $target);
            }

            return $this->templateRepository->findOrFail($target->getKey());
        });
    }

    private function cloneTemplateFromLiveSource(Template $source, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($source, $actorId) {
            $this->templateRepository->loadBlocks($source);
            $this->templateRepository->loadReviewers($source, withDocumentReviewers: true);
            $cloneVisibility = $source->visibility_level instanceof TemplateVisibilityLevel
                ? $source->visibility_level->value
                : $source->visibility_level;
            $cloneName = $source->name.' (copia)';
            $this->assertTemplateMetadataInvariants(
                $cloneName,
                $source->delivery_deadline,
                $cloneVisibility,
                $source->document_delivery_deadline,
            );

            $target = $this->templateRepository->create([
                'process_id' => $source->process_id,
                'name' => $cloneName,
                'description' => $source->description,
                'visibility_level' => $cloneVisibility,
                'delivery_deadline' => $source->delivery_deadline,
                'document_delivery_deadline' => $source->document_delivery_deadline,
                'study_type_id' => $source->study_type_id,
                'study_id' => $source->study_id,
                'module_id' => $source->module_id,
                'team_id' => $source->team_id,
                'created_by' => $actorId,
                'status' => 'draft',
                'review_stages' => $source->review_stages,
                'review_mode' => $source->review_mode,
                'document_review_mode' => $this->resolveStoredDocumentReviewModeForClone($source),
            ]);

            $this->templateRepository->replicateBlocks($source, $target);

            $this->templateReviewerRepository->copyReviewersFromTemplate($source, $target);

            return $this->templateRepository->findOrFail($target->getKey());
        });
    }

    /**
     * Resuelve la última versión publicada con metadatos si el ganador por meta no produce snapshot usable.
     *
     * @return ?array{
     *     kind: 'entity'|'legacy',
     *     template_meta: array<string, mixed>,
     *     blocks: array<int, array<string, mixed>>,
     *     reviewers_from_snapshot: bool,
     *     template_reviewers: list<array<string, mixed>>,
     *     document_reviewers: list<array<string, mixed>>
     * }
     */
    private function resolveLatestPublishedTemplateSnapshotForClone(string $templateId): ?array
    {
        $winner = $this->entityVersionRepository->findLatestPublishedMetaForVersionable(Template::class, $templateId);
        if ($winner === null) {
            return $this->resolveFallbackPublishedTemplateSnapshot($templateId);
        }

        $versionNumber = $winner['version_number'];

        $entityRow = $this->entityVersionRepository->findPublishedForEntityVersionNumber(
            Template::class,
            $templateId,
            $versionNumber,
        );

        if ($entityRow !== null && is_array($entityRow->snapshot_data)) {
            $payload = $this->buildEntityClonePayloadFromSnapshotData($entityRow->snapshot_data);
            if ($payload !== null) {
                return $payload;
            }
        }

        return $this->resolveFallbackPublishedTemplateSnapshot($templateId);
    }

    /**
     * Última versión publicada con bloques materializables si el ganador por meta no produce snapshot usable.
     *
     * @return ?array{
     *     kind: 'entity'|'legacy',
     *     template_meta: array<string, mixed>,
     *     blocks: array<int, array<string, mixed>>,
     *     reviewers_from_snapshot: bool,
     *     template_reviewers: list<array<string, mixed>>,
     *     document_reviewers: list<array<string, mixed>>
     * }
     */
    private function resolveFallbackPublishedTemplateSnapshot(string $templateId): ?array
    {
        foreach ($this->entityVersionRepository->listPublishedForEntityOrdered(Template::class, $templateId)->sortByDesc('version_number') as $ev) {
            if (! is_array($ev->snapshot_data)) {
                continue;
            }
            $payload = $this->buildEntityClonePayloadFromSnapshotData($ev->snapshot_data);
            if ($payload !== null) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * Construye el payload de clonación de la plantilla.
     *
     * @param  array<string, mixed>  $data
     * @return ?array{
     *     kind: 'entity',
     *     template_meta: array<string, mixed>,
     *     blocks: array<int, array<string, mixed>>,
     *     reviewers_from_snapshot: bool,
     *     template_reviewers: list<array<string, mixed>>,
     *     document_reviewers: list<array<string, mixed>>
     * }
     */
    private function buildEntityClonePayloadFromSnapshotData(array $data): ?array
    {
        $blocks = isset($data['blocks']) && is_array($data['blocks']) ? $data['blocks'] : [];
        if ($blocks === []) {
            return null;
        }

        $hasReviewersSection = isset($data['reviewers']) && is_array($data['reviewers']);
        $rp = $hasReviewersSection ? $data['reviewers'] : [];

        return [
            'kind' => 'entity',
            'template_meta' => isset($data['template']) && is_array($data['template']) ? $data['template'] : [],
            'blocks' => $blocks,
            'reviewers_from_snapshot' => $hasReviewersSection,
            'template_reviewers' => $hasReviewersSection && isset($rp['template_reviewers']) && is_array($rp['template_reviewers'])
                ? $rp['template_reviewers']
                : [],
            'document_reviewers' => $hasReviewersSection && isset($rp['document_reviewers']) && is_array($rp['document_reviewers'])
                ? $rp['document_reviewers']
                : [],
        ];
    }

    /**
     * Clona el nombre base de la plantilla.
     *
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateNameBase(string $kind, array $templateMeta, Template $source): string
    {
        if ($kind === 'entity' && isset($templateMeta['name']) && is_string($templateMeta['name']) && $templateMeta['name'] !== '') {
            return $templateMeta['name'];
        }

        return (string) $source->name;
    }

    /**
     * Clona el nombre base de la plantilla.
     *
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateDescription(string $kind, array $templateMeta, Template $source): ?string
    {
        if ($kind === 'entity' && array_key_exists('description', $templateMeta)) {
            return $templateMeta['description'] !== null ? (string) $templateMeta['description'] : null;
        }

        return $source->description;
    }

    /**
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateDeliveryDeadline(string $kind, array $templateMeta, Template $source): mixed
    {
        if ($kind === 'entity' && array_key_exists('delivery_deadline', $templateMeta)) {
            return $templateMeta['delivery_deadline'];
        }

        return $source->delivery_deadline;
    }

    /**
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateDocumentDeliveryDeadline(string $kind, array $templateMeta, Template $source): mixed
    {
        if ($kind === 'entity' && array_key_exists('document_delivery_deadline', $templateMeta)) {
            return $templateMeta['document_delivery_deadline'];
        }

        return $source->document_delivery_deadline;
    }

    /**
     * Clona el valor de un FK de la plantilla.
     *
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateNullableFk(string $kind, array $templateMeta, Template $source, string $key): mixed
    {
        if ($kind === 'entity' && array_key_exists($key, $templateMeta)) {
            return $templateMeta[$key];
        }

        return $source->{$key};
    }

    /**
     * Normaliza el nivel de visibilidad de la plantilla.
     *
     * @param  TemplateVisibilityLevel|string  $level
     */
    private function normalizeTemplateVisibilityLevelForClone(string $kind, array $templateMeta, Template $source): string
    {
        if ($kind === 'entity' && array_key_exists('visibility_level', $templateMeta)) {
            return $this->normalizeTemplateVisibilityLevelValue($templateMeta['visibility_level']);
        }

        return $this->normalizeTemplateVisibilityLevelValue($source->visibility_level);
    }

    /**
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateReviewStages(string $kind, array $templateMeta, Template $source): int
    {
        if ($kind === 'entity' && array_key_exists('review_stages', $templateMeta)) {
            return (int) $templateMeta['review_stages'];
        }

        return (int) $source->review_stages;
    }

    /**
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateReviewMode(string $kind, array $templateMeta, Template $source): string
    {
        if (
            $kind === 'entity'
            && isset($templateMeta['review_mode'])
            && is_string($templateMeta['review_mode'])
            && in_array($templateMeta['review_mode'], ['sequential', 'parallel'], true)
        ) {
            return $templateMeta['review_mode'];
        }

        return (string) $source->review_mode;
    }

    /**
     * @param  array<string, mixed>  $templateMeta
     */
    private function cloneTemplateDocumentReviewMode(string $kind, array $templateMeta, Template $source): string
    {
        if (
            $kind === 'entity'
            && isset($templateMeta['document_review_mode'])
            && is_string($templateMeta['document_review_mode'])
            && in_array($templateMeta['document_review_mode'], ['sequential', 'parallel'], true)
        ) {
            return $templateMeta['document_review_mode'];
        }

        if (
            $kind === 'entity'
            && isset($templateMeta['review_mode'])
            && is_string($templateMeta['review_mode'])
            && in_array($templateMeta['review_mode'], ['sequential', 'parallel'], true)
        ) {
            return $templateMeta['review_mode'];
        }

        return $this->resolveStoredDocumentReviewModeForClone($source);
    }

    private function resolveStoredDocumentReviewModeForClone(Template $source): string
    {
        $this->templateRepository->loadHeadVersion($source);
        $fields = data_get($source->headVersion?->snapshot_data, TemplateHeadSnapshot::JSON_TEMPLATE_KEY);

        if (is_array($fields)) {
            return TemplateHeadSnapshot::resolveDocumentReviewMode($fields);
        }

        return (string) $source->review_mode;
    }

    private function normalizeTemplateVisibilityLevelValue(mixed $level): string
    {
        if ($level instanceof TemplateVisibilityLevel) {
            return $level->value;
        }
        if (is_string($level) && $level !== '') {
            return $level;
        }

        return TemplateVisibilityLevel::Personal->value;
    }

    /**
     * Aserta las invariantes de los metadatos de la plantilla.
     * Lanza excepción si alguna invariante no se cumple.
     *
     * @param  Carbon|string|null  $deliveryDeadline
     * @param  TemplateVisibilityLevel|string  $visibilityLevel
     */
    private function assertTemplateMetadataInvariants(
        string $name,
        mixed $deliveryDeadline,
        mixed $visibilityLevel,
        mixed $documentDeliveryDeadline,
    ): void
    {
        if (trim($name) === '') {
            throw ValidationException::withMessages([
                'name' => [__('validation.template.name_required')],
            ]);
        }

        if ($deliveryDeadline === null || (is_string($deliveryDeadline) && trim($deliveryDeadline) === '')) {
            throw ValidationException::withMessages([
                'delivery_deadline' => [__('validation.template.deadline_required')],
            ]);
        }

        if ($documentDeliveryDeadline === null || (is_string($documentDeliveryDeadline) && trim($documentDeliveryDeadline) === '')) {
            throw ValidationException::withMessages([
                'document_delivery_deadline' => [__('validation.template.document_deadline_required')],
            ]);
        }

        $templateDeadlineAt = Carbon::parse((string) $deliveryDeadline);
        $documentDeadlineAt = Carbon::parse((string) $documentDeliveryDeadline);
        if ($documentDeadlineAt->lt($templateDeadlineAt)) {
            throw ValidationException::withMessages([
                'document_delivery_deadline' => [__('validation.template.document_deadline_before_template')],
            ]);
        }

        $normalizedVisibility = $visibilityLevel instanceof TemplateVisibilityLevel
            ? $visibilityLevel->value
            : (is_string($visibilityLevel) ? trim($visibilityLevel) : '');
        if ($normalizedVisibility === '') {
            throw ValidationException::withMessages([
                'visibility_level' => [__('validation.template.visibility_required')],
            ]);
        }
    }

    /**
     * Normaliza los atributos de actualización contra el scope de la plantilla.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeUpdateAttributesAgainstTemplateScope(Template $template, array $attributes): array
    {
        $visibilityValue = $this->normalizeTemplateVisibilityLevelValue($template->visibility_level);
        $visibilityLevel = TemplateVisibilityLevel::from($visibilityValue);

        $ctx = new AcademicScopeContext(
            visibilityLevel: $visibilityLevel,
            templateStudyTypeId: is_string($template->study_type_id) && $template->study_type_id !== '' ? $template->study_type_id : null,
            templateStudyId: is_string($template->study_id) && $template->study_id !== '' ? $template->study_id : null,
            templateModuleId: is_string($template->module_id) && $template->module_id !== '' ? $template->module_id : null,
            entityStudyTypeId: is_string($template->study_type_id) && $template->study_type_id !== '' ? $template->study_type_id : null,
            entityStudyId: is_string($template->study_id) && $template->study_id !== '' ? $template->study_id : null,
            entityModuleId: is_string($template->module_id) && $template->module_id !== '' ? $template->module_id : null,
            onModuleConflict: 'La plantilla debe mantenerse en el mismo módulo.',
            onStudyConflict: 'La plantilla debe mantenerse en el mismo estudio.',
            onModuleStudyMismatch: 'El módulo debe pertenecer al mismo estudio de la plantilla.',
            onModuleTypeMismatch: 'El módulo debe pertenecer a un estudio del mismo tipo que la plantilla.',
            onStudyTypeMismatch: 'El estudio debe pertenecer al mismo tipo de estudio de la plantilla.',
            onModuleNotFound: 'El módulo seleccionado no existe.',
            onStudyModuleMismatch: 'El estudio indicado no corresponde con el módulo seleccionado.',
            strictTemplateIds: true,
        );

        return AcademicScopeNormalizer::normalize($this->academicHierarchyRepository, $ctx, $attributes);
    }

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(string $templateId, SyncUsersDto $dto): void
    {
        $this->templateReviewerAssignmentService->syncReviewers($templateId, $dto);
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(string $templateId, SyncUsersDto $dto): void
    {
        $this->templateReviewerAssignmentService->syncDocumentReviewers($templateId, $dto);
    }

    /**
     * Verifica si un usuario es revisor activo para una plantilla en estado in_review.
     */
    public function isUserActiveReviewerForTemplate(string $templateId, string $userId): bool
    {
        $template = $this->templateRepository->findOrFail($templateId);

        return $template->status === 'in_review'
            && $this->templateReviewerRepository->existsReviewerForTemplate($templateId, $userId);
    }

    /**
     * Determina si el viewer debe recibir el snapshot publicado o el contenido vivo,
     * y si es revisor asignado activo. Encapsula la lógica de branching del show()
     * del TemplateController — espejo de DocumentService::resolveDocumentViewerContext().
     *
     * @return array{serve_published_snapshot: bool, is_assigned_reviewer: bool}
     */
    public function resolveTemplateViewerContext(Template $model, string $templateId, string $viewerId): array
    {
        // Admin de SOLO LECTURA: ve el contenido VIVO de cualquier plantilla (sin forzar el
        // snapshot publicado). No se le trata como creador ni revisor: no puede mutar nada.
        $viewer = auth()->user();
        if ($viewer instanceof JwtUser && $viewer->canReadAll()) {
            $isAssignedReviewer = $model->status === 'in_review'
                && $this->templateReviewerRepository->existsReviewerForTemplate($templateId, $viewerId);

            return [
                'serve_published_snapshot' => false,
                'is_assigned_reviewer' => $isAssignedReviewer,
            ];
        }

        $isCreator = (string) $model->created_by === $viewerId;
        $isAssignedReviewer = false;
        $servePublishedSnapshot = false;

        if (! $isCreator && in_array($model->status, ['draft', 'in_review', 'rejected'], true)) {
            $isAssignedReviewer = $model->status === 'in_review'
                && $this->templateReviewerRepository->existsReviewerForTemplate($templateId, $viewerId);

            if (! $isAssignedReviewer) {
                $servePublishedSnapshot = true;
            }
        }

        return [
            'serve_published_snapshot' => $servePublishedSnapshot,
            'is_assigned_reviewer' => $isAssignedReviewer,
        ];
    }
}
