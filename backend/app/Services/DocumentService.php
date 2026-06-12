<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\ApplyTemplateMigrationDto;
use App\DTOs\Documents\BlockDisplayDto;
use App\DTOs\Documents\BlockUpdateDto;
use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\DTOs\Documents\CreationOptionDto;
use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\DTOs\Documents\DocumentDto;
use App\DTOs\Documents\DocumentFilterDto;
use App\DTOs\Documents\DocumentMigrationPayloadDto;
use App\DTOs\Documents\ReviewerPoolDto;
use App\DTOs\Documents\TemplateVersionStatusDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\DTOs\Versioning\DocumentVersionDetailDto;
use App\DTOs\Versioning\DocumentVersionDto;
use App\DTOs\Versioning\DocumentVersionSummaryDto;
use App\DTOs\Versioning\WorkingRevisionConflictDto;
use App\Enums\TemplateVisibilityLevel;
use App\Events\DocumentSubmittedForReview;
use App\Events\OwnershipTransferred;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Models\DocumentVersion;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Support\AcademicScopeContext;
use App\Support\AcademicScopeNormalizer;
use App\Support\CloneDeadlinePolicy;
use App\Support\DocumentReviewModeResolver;
use App\Support\ReviewValidationNotificationRecipients;
use App\Support\VersionSubmissionChangelog;
use App\Support\WorkingRevisionConflictResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maya\Http\Pagination\PaginatedDto;
use Maya\Messaging\Publishers\NotificationPublisher;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly SnapshotServiceInterface $snapshotService,
        private readonly DocumentBlockService $documentBlockService,
        private readonly DocumentVersionService $documentVersionService,
        private readonly DocumentShareService $documentShareService,
        private readonly DocumentStateService $documentStateService,
        private readonly DocumentReviewService $documentReviewService,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly DocumentBlockRepositoryInterface $documentBlockRepository,
        private readonly TemplateContextResolver $contextResolver,
        private readonly AcademicHierarchyRepositoryInterface $academicHierarchyRepository,
        private readonly TeamReadRepositoryInterface $teamReadRepository,
        private readonly NotificationPublisher $notificationPublisher,
        private readonly DocumentReviewModeResolver $documentReviewModeResolver,
        private readonly DocumentMigrationPayloadResolver $migrationPayloadResolver,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly EntityVersionDestroyService $entityVersionDestroyService,
    ) {}

    /**
     * Canónico: devuelve el DTO del documento. Lanza ModelNotFoundException
     * si no existe.
     */
    public function findOrFail(string $id): DocumentDto
    {
        return DocumentDto::fromModel($this->documentRepository->findOrFail($id));
    }

    /**
     * Variante de uso interno: devuelve el Model. Necesario cuando el caller
     * adjunta atributos derivados (`can_clone`, `review_mode`, etc.) antes de
     * representar como DTO, o cuando invoca `authorize($ability, $model)`.
     */
    public function findModelOrFail(string $id): Document
    {
        return $this->documentRepository->findOrFail($id);
    }

    public function findModelOrFailWithoutUserAccess(string $id): Document
    {
        return $this->documentRepository->findOrFailForRefreshAfterMutation($id);
    }

    public function resolveDocumentWithPublishedFallback(string $id): Document
    {
        try {
            return $this->findModelOrFail($id);
        } catch (ModelNotFoundException) {
            $doc = $this->findModelOrFailWithoutUserAccess($id);
            if (! $this->hasPublishedSnapshot($doc->id)) {
                abort(404);
            }

            return $doc;
        }
    }

    public function hasPublishedSnapshot(string $id): bool
    {
        return $this->entityVersionRepository->findLatestPublishedMetaForVersionable(Document::class, $id) !== null;
    }

    public function findLatestPublishedVersion(string $documentId): ?EntityVersion
    {
        return $this->entityVersionRepository->findLatestPublishedForEntity(Document::class, $documentId);
    }

    /**
     * Crea un documento a partir de un DTO.
     */
    public function create(CreateDocumentDto $dto): Document
    {
        $this->templateRepository->findOrFail($dto->templateId);

        if ($dto->templateVersionId !== null) {
            $ev = $this->entityVersionRepository->findPublishedByIdForVersionable(
                $dto->templateVersionId,
                Template::class,
                $dto->templateId,
            );
            if ($ev === null) {
                throw ValidationException::withMessages([
                    'template_version_id' => ['La versión publicada no existe o no pertenece a esta plantilla.'],
                ]);
            }
        } else {
            $ev = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $dto->templateId);
            if ($ev === null) {
                throw ValidationException::withMessages([
                    'template_id' => ['La plantilla no tiene versiones publicadas; no se puede crear un documento.'],
                ]);
            }
        }

        $snapshot = $this->documentBlockService->templatePublicationDefinitionRowsFromEntityVersion((string) $ev->id);
        if ($snapshot === []) {
            throw ValidationException::withMessages([
                'template_id' => ['La versión de plantilla no contiene bloques.'],
            ]);
        }

        $migrated = $dto->migratedBlockContent ?? [];

        $blockRows = collect($snapshot)
            ->sortBy(fn ($b) => $b['sort_order'] ?? 0)
            ->map(function (array $b) use ($migrated): array {
                $state = (string) ($b['block_state'] ?? 'editable');
                $templateBlockId = (string) $b['id'];

                // Editables: vacío en BD; el docente ve default_content solo como guía en UI.
                $content = $state === 'editable' ? null : ($b['default_content'] ?? null);

                // Paso de migración: precarga el contenido antiguo salvo en bloques bloqueados.
                if ($state !== 'locked' && array_key_exists($templateBlockId, $migrated)) {
                    $content = $migrated[$templateBlockId];
                }

                return [
                    'template_block_id' => $templateBlockId,
                    'content' => $content,
                    'sort_order' => (int) ($b['sort_order'] ?? 0),
                ];
            })
            ->values()
            ->all();

        $templateMeta = is_array($ev->snapshot_data ?? null) ? ($ev->snapshot_data['template'] ?? null) : null;
        $ctx = $this->contextResolver->resolve($dto, is_array($templateMeta) ? $templateMeta : null);

        return $this->documentRepository->createDocumentWithBlocks([
            'process_id' => $dto->processId,
            'template_id' => $dto->templateId,
            'template_version_id' => (string) $ev->id,
            'title' => $dto->title,
            'study_type_id' => $ctx->studyTypeId,
            'study_id' => $ctx->studyId,
            'module_id' => $ctx->moduleId,
            'team_id' => $ctx->teamId,
            'delivery_deadline' => $dto->deliveryDeadline,
            'created_by' => $dto->createdBy,
            'owner_id' => $dto->ownerId,
            'status' => 'draft',
        ], $blockRows);
    }

    /**
     * Clona un documento origen hacia uno nuevo en borrador.
     *
     * Si el origen está en ciclo de trabajo (draft/in_review/rejected), copia bloques vivos.
     * Si está publicado y existe versión publicada en {@see DocumentVersion}, materializa
     * desde el último snapshot con trigger_event «published»; si no, desde el estado vivo.
     */
    public function clone(string $sourceDocumentId, string $actorId): Document
    {
        return $this->documentRepository->transaction(function () use ($sourceDocumentId, $actorId) {
            $source = $this->documentRepository->findOrFail($sourceDocumentId);

            if (! in_array((string) $source->status, ['draft', 'in_review', 'rejected'], true)) {
                $publishedSnapshot = $this->documentRepository->findLatestPublishedDocumentVersion($sourceDocumentId);

                $resolvedPublish = $publishedSnapshot !== null ? $publishedSnapshot->resolvedSnapshotData() : null;
                if ($publishedSnapshot !== null && is_array($resolvedPublish)) {
                    $snap = $resolvedPublish;
                    $docSnap = isset($snap['document']) && is_array($snap['document']) ? $snap['document'] : [];
                    $blockSnapshots = isset($snap['blocks']) && is_array($snap['blocks']) ? $snap['blocks'] : [];
                    $blockRows = $this->cloneBlockRowsFromSnapshotBlocks($blockSnapshots, $actorId);

                    if ($blockRows !== []) {
                        $attributes = $this->cloneDocumentAttributesFromPublishedSnapshot($source, $docSnap, $actorId);

                        return $this->documentRepository->createDocumentWithBlocks(
                            $attributes,
                            $blockRows,
                        );
                    }
                }
            }

            $source->load(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

            /** @var list<array{template_block_id: string, content: mixed, sort_order: int, is_filled?: bool, last_edited_by?: ?string}> $blockRows */
            $blockRows = $source->blocks->map(function (DocumentBlock $b) use ($actorId): array {
                $row = [
                    'template_block_id' => (string) $b->template_block_id,
                    'content' => $b->content,
                    'sort_order' => (int) $b->sort_order,
                    'is_filled' => (bool) $b->is_filled,
                ];
                if ($b->is_filled) {
                    $row['last_edited_by'] = $actorId;
                }

                return $row;
            })->all();

            $templateId = (string) $source->template_id;
            $publishedTemplateVersionId = $this->resolvePublishedTemplateVersionIdForClone(
                $templateId,
                is_string($source->template_version_id) ? $source->template_version_id : null,
            );
            $this->assertDocumentMetadataInvariantsForMutation(
                (string) $source->title,
                $source->delivery_deadline,
            );

            return $this->documentRepository->createDocumentWithBlocks([
                'process_id' => $source->process_id,
                'template_id' => $templateId,
                'template_version_id' => $publishedTemplateVersionId,
                'title' => $source->title.' (copia)',
                'study_type_id' => $source->study_type_id,
                'study_id' => $source->study_id,
                'module_id' => $source->module_id,
                'team_id' => $source->team_id,
                'delivery_deadline' => $this->clearPastDeliveryDeadline($source->delivery_deadline),
                'created_by' => $actorId,
                'owner_id' => $actorId,
                'status' => 'draft',
            ], $blockRows);
        });
    }

    /**
     * @param  array<int, mixed>  $blockSnapshots
     * @return list<array{template_block_id: string, content: mixed, sort_order: int, is_filled?: bool, last_edited_by?: ?string}>
     */
    private function cloneBlockRowsFromSnapshotBlocks(array $blockSnapshots, string $actorId): array
    {
        $rows = [];
        foreach ($blockSnapshots as $b) {
            if (! is_array($b)) {
                continue;
            }

            $tid = $b['template_block_id'] ?? null;
            if (! is_string($tid) || $tid === '') {
                continue;
            }

            $isFilled = (bool) ($b['is_filled'] ?? false);
            $row = [
                'template_block_id' => $tid,
                'content' => $b['content'] ?? null,
                'sort_order' => isset($b['sort_order']) ? (int) $b['sort_order'] : 0,
                'is_filled' => $isFilled,
            ];
            if ($isFilled) {
                $row['last_edited_by'] = $actorId;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Metadatos para el documento copiado: ancla de plantilla y contexto académico según el último snapshot publicado;
     * proceso y fechas de programación se heredan del documento vivo (no figuran en el snapshot).
     *
     * @param  array<string, mixed>  $docSnap
     * @return array<string, mixed>
     */
    private function cloneDocumentAttributesFromPublishedSnapshot(Document $source, array $docSnap, string $actorId): array
    {
        $processId = isset($docSnap['process_id']) && is_string($docSnap['process_id']) && $docSnap['process_id'] !== ''
            ? $docSnap['process_id']
            : $source->process_id;
        $templateId = isset($docSnap['template_id']) && is_string($docSnap['template_id']) && $docSnap['template_id'] !== ''
            ? $docSnap['template_id']
            : (string) $source->template_id;

        $templateVersionId = $docSnap['template_version_id'] ?? $source->template_version_id;
        if ($templateVersionId !== null && ! is_string($templateVersionId)) {
            $templateVersionId = $source->template_version_id;
        }
        $templateVersionId = $this->resolvePublishedTemplateVersionIdForClone(
            $templateId,
            is_string($templateVersionId) ? $templateVersionId : null,
        );

        $titleBase = isset($docSnap['title']) && is_string($docSnap['title'])
            ? $docSnap['title']
            : (string) $source->title;
        $deliveryDeadline = array_key_exists('delivery_deadline', $docSnap)
            ? $docSnap['delivery_deadline']
            : $source->delivery_deadline;
        $this->assertDocumentMetadataInvariantsForMutation($titleBase, $deliveryDeadline);

        return [
            'process_id' => $processId,
            'template_id' => $templateId,
            'template_version_id' => $templateVersionId,
            'title' => $titleBase.' (copia)',
            'study_type_id' => array_key_exists('study_type_id', $docSnap) ? $docSnap['study_type_id'] : $source->study_type_id,
            'study_id' => array_key_exists('study_id', $docSnap) ? $docSnap['study_id'] : $source->study_id,
            'module_id' => array_key_exists('module_id', $docSnap) ? $docSnap['module_id'] : $source->module_id,
            'team_id' => array_key_exists('team_id', $docSnap) ? $docSnap['team_id'] : $source->team_id,
            // Un clon es para un curso nuevo: una fecha límite ya vencida se limpia
            // para que el editor obligue a fijar una nueva (validación de paso 1).
            'delivery_deadline' => $this->clearPastDeliveryDeadline($deliveryDeadline),
            'created_by' => $actorId,
            'owner_id' => $actorId,
            'status' => 'draft',
        ];
    }

    private function resolvePublishedTemplateVersionIdForClone(string $templateId, ?string $candidateVersionId): ?string
    {
        if (is_string($candidateVersionId) && $candidateVersionId !== '') {
            $candidate = $this->entityVersionRepository->findPublishedByIdForVersionable(
                $candidateVersionId,
                Template::class,
                $templateId,
            );
            if ($candidate !== null && (int) $candidate->version_number > 0) {
                return (string) $candidate->id;
            }
        }

        $latestPublished = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
        if ($latestPublished === null || (int) $latestPublished->version_number <= 0) {
            return null;
        }

        return (string) $latestPublished->id;
    }

    /**
     * Actualiza metadatos editables del documento.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $documentId, array $attributes): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if (! in_array($document->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden editar metadatos de documentos en borrador o rechazados.'],
            ]);
        }

        $merged = $this->normalizeUpdateAttributesAgainstDocumentAndTemplate($document, $attributes);
        $this->assertDocumentMetadataInvariantsForMutation(
            is_string($merged['title'] ?? null) ? $merged['title'] : (string) $document->title,
            $merged['delivery_deadline'] ?? $document->delivery_deadline,
        );

        return $this->documentRepository->updateDocumentMetadata($document, $merged);
    }

    /**
     * Borrado lógico del documento.
     * Si existe versión publicada + borrador activo, descarta el borrador y restaura la publicada.
     * Si nunca fue publicado, elimina la entidad completa.
     */
    public function delete(string $documentId, string $actorId): void
    {
        $document = $this->documentRepository->findOrFail($documentId);

        $latestPublished = $this->entityVersionRepository->findLatestPublishedForEntity(Document::class, $documentId);
        if ($latestPublished !== null) {
            $document->loadMissing('headVersion');
            $head = $document->headVersion;

            if ($head !== null && (int) $head->version_number === 0 && in_array((string) $head->status, ['draft', 'in_review'], true)) {
                // Published version exists + working draft → discard draft, restore published.
                $this->destroyVersion($documentId, (string) $head->id, $actorId);

                return;
            }

            throw ValidationException::withMessages([
                'document' => ['No se puede eliminar un documento publicado sin versión de trabajo activa.'],
            ]);
        }

        $this->documentRepository->delete($document);
    }

    /**
     * Opciones de creación de documento disponibles para un módulo.
     *
     * @return list<CreationOptionDto>
     */
    public function creationOptionsForModule(string $moduleId): array
    {
        $templates = $this->templateRepository->listPublishedByModule($moduleId);
        if ($templates->isEmpty()) {
            return [];
        }

        $templateIds = $templates->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        // Batch: one query for the latest published version of each template.
        $publishedById = $this->entityVersionRepository->findLatestPublishedIdsByVersionables(
            Template::class,
            $templateIds,
        );

        // Batch: one query for all distinct team names.
        $teamIds = $templates->pluck('team_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
        $teamNames = $this->teamReadRepository->getTeamNamesByIds($teamIds);

        $options = [];
        foreach ($templates as $template) {
            $templateId = (string) $template->id;
            $publishedVersionId = $publishedById[$templateId] ?? null;
            if ($publishedVersionId === null) {
                continue;
            }

            $options[] = new CreationOptionDto(
                templateId: (string) $template->id,
                templateVersionId: $publishedVersionId,
                processId: (string) $template->process_id,
                name: (string) $template->name,
                description: $template->description,
                visibilityLevel: $template->visibility_level instanceof TemplateVisibilityLevel
                    ? $template->visibility_level->value
                    : (string) $template->visibility_level,
                teamId: $template->team_id !== null ? (string) $template->team_id : null,
                teamName: $template->team_id !== null
                    ? ($teamNames[(string) $template->team_id] ?? null)
                    : null,
            );
        }

        return $options;
    }

    /**
     * Crea un documento desde la vista de módulo resolviendo plantilla/version disponibles.
     */
    public function createFromModule(
        string $moduleId,
        string $creatorId,
        string $processId,
        ?string $templateVersionId = null,
        ?string $deliveryDeadline = null,
    ): Document {
        $options = $this->creationOptionsForModule($moduleId);
        if ($options === []) {
            throw ValidationException::withMessages([
                'module_id' => ['El módulo no tiene plantillas publicadas disponibles.'],
            ]);
        }

        $selected = null;
        if ($templateVersionId !== null) {
            foreach ($options as $option) {
                if ($option->templateVersionId === $templateVersionId) {
                    $selected = $option;
                    break;
                }
            }

            if ($selected === null) {
                throw ValidationException::withMessages([
                    'template_version_id' => ['La versión seleccionada no está disponible para el módulo.'],
                ]);
            }
        } elseif (count($options) === 1) {
            $selected = $options[0];
        } else {
            throw ValidationException::withMessages([
                'template_version_id' => ['Debe seleccionar una plantilla cuando existen varias opciones.'],
            ]);
        }

        if ($selected->processId !== $processId) {
            throw ValidationException::withMessages([
                'process_id' => ['El proceso no corresponde a la plantilla seleccionada para el módulo.'],
            ]);
        }

        $moduleContext = $this->documentRepository->findModuleContext($moduleId);
        if ($moduleContext === null) {
            throw ValidationException::withMessages([
                'module_id' => ['El módulo no existe.'],
            ]);
        }

        return $this->create(new CreateDocumentDto(
            templateId: $selected->templateId,
            title: 'Nueva Programación Didáctica',
            createdBy: $creatorId,
            ownerId: $creatorId,
            processId: $processId,
            studyTypeId: $moduleContext['study_type_id'],
            studyId: $moduleContext['study_id'],
            moduleId: $moduleContext['module_id'],
            deliveryDeadline: $deliveryDeadline,
            templateVersionId: $selected->templateVersionId,
        ));
    }

    /**
     * Comparación ligera entre la versión de plantilla anclada al documento y la última publicada.
     */
    public function templateVersionStatus(string $documentId): TemplateVersionStatusDto
    {
        $document = $this->documentRepository->findOrFail($documentId);

        $currentFull = $this->resolveCurrentPublishedTemplateVersionMeta($document);
        $latestFull = $this->resolveLatestPublishedTemplateVersionMeta((string) $document->template_id);

        $current = $currentFull !== null
            ? [
                'id' => $currentFull['id'],
                'version_number' => $currentFull['version_number'],
            ]
            : null;

        $hasUpdate = $currentFull !== null
            && $latestFull !== null
            && $latestFull['version_number'] > $currentFull['version_number'];

        return new TemplateVersionStatusDto(
            currentVersion: $current,
            latestVersion: $latestFull,
            hasUpdate: $hasUpdate,
            changelog: $hasUpdate ? $latestFull['changelog'] : null,
        );
    }

    public function migrationPayload(string $sourceDocumentId): DocumentMigrationPayloadDto
    {
        return $this->migrationPayloadResolver->resolve($sourceDocumentId);
    }

    /**
     * Última versión publicada de plantilla ({@see EntityVersion}).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    private function resolveLatestPublishedTemplateVersionMeta(string $templateId): ?array
    {
        return $this->entityVersionRepository->findLatestPublishedMetaForVersionable(Template::class, $templateId);
    }

    /**
     * Meta de la publicación anclada: {@see EntityVersion} (columna {@code template_version_id}).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    private function resolveCurrentPublishedTemplateVersionMeta(Document $document): ?array
    {
        $versionId = $document->template_version_id;

        if (! is_string($versionId) || $versionId === '') {
            return null;
        }

        $entity = $this->entityVersionRepository->findPublishedMetaByIdForVersionable(
            $versionId,
            Template::class,
            (string) $document->template_id,
        );
        if ($entity !== null) {
            return $entity;
        }

        $ev = $this->entityVersionRepository->findPublishedByIdAndType($versionId, Template::class);

        if ($ev === null) {
            return null;
        }

        return [
            'id' => (string) $ev->id,
            'version_number' => (int) $ev->version_number,
            'changelog' => (string) ($ev->changelog ?? ''),
        ];
    }

    /**
     * Crea o actualiza un compartido del documento (solo titular vía policy en controlador).
     *
     * @return array{user_id: string, permission: string, granted_by: string}
     */
    public function upsertDocumentShare(
        string $documentId,
        string $targetUserId,
        string $permission,
        string $actorId,
    ): array {
        return $this->documentShareService->upsertDocumentShare(
            $documentId,
            $targetUserId,
            $permission,
            $actorId,
        );
    }

    /**
     * Elimina un compartido (idempotente si no existía).
     */
    public function removeDocumentShare(string $documentId, string $targetUserId, string $actorId): void
    {
        $this->documentShareService->removeDocumentShare($documentId, $targetUserId, $actorId);
    }

    /**
     * Anota en cada documento si el visor accede vía `document_shares` y con qué permiso (listado / detalle).
     *
     * @param  Collection<int, Document>  $documents
     */
    public function attachShareMetadataForViewer(Collection $documents, string $viewerId): void
    {
        $this->documentShareService->attachShareMetadataForViewer($documents, $viewerId);
    }

    /**
     * Listado paginado de documentos con filtros de dominio (ADR-C).
     *
     * Enriquece los modelos de la página actual antes de mapear a DTO (metadatos de
     * publicación, compartición, revisor, etc.). El controller puede pasar un callback
     * para adjuntar presentación (`can_clone`, `team`, …) antes del mapeo.
     *
     * @param  callable(Collection<int, Document>): void|null  $beforeMap
     * @return PaginatedDto<DocumentDto>
     */
    public function paginate(
        DocumentFilterDto $filter,
        string $viewerId,
        ?callable $beforeMap = null,
    ): PaginatedDto {
        $paginator = $this->documentRepository->paginate($filter);
        /** @var Collection<int, Document> $documents */
        $documents = collect($paginator->items());

        if ($documents->isNotEmpty()) {
            $this->attachLatestPublishedVersionMeta($documents);
            $this->attachTemplateVersionNumbers($documents);
            $this->attachShareMetadataForViewer($documents, $viewerId);
            $this->attachIsAssignedReviewerMeta($documents, $viewerId);
            if ($beforeMap !== null) {
                $beforeMap($documents);
            }
        }

        return PaginatedDto::fromPaginator(
            $paginator,
            static fn (Document $doc) => DocumentDto::fromModel($doc),
        );
    }

    /**
     * Lista documentos visibles para el usuario actual ordenados por fecha de creación descendente.
     *
     * @return Collection<int, Document>
     */
    public function listOrderedByCreatedAtDesc(?string $processId = null): Collection
    {
        return $this->documentRepository->listOrderedByCreatedAtDesc($processId);
    }

    /**
     * Bloques para mostrar/editar: definición según {@see Document::$template_version_id} y contenido en document_blocks.
     *
     * @return list<BlockDisplayDto>
     */
    public function blocksForDisplay(Document $document): array
    {
        return $this->documentBlockService->blocksForDisplay((string) $document->id);
    }

    /**
     * Actualiza el contenido de un bloque de documento.
     */
    public function updateBlock(UpdateDocumentBlockDto $dto): BlockUpdateDto
    {
        return $this->documentBlockService->updateBlock($dto);
    }

    public function deleteOptionalBlock(DeleteDocumentBlockDto $dto): void
    {
        $this->documentBlockService->deleteOptionalBlock($dto);
    }

    /**
     * Transiciona el documento a un nuevo estado y emite el evento de dominio DocumentStateChanged.
     *
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = []): Document
    {
        return $this->documentStateService->transition($documentId, $newStatus, $actorId, $extraAttributes);
    }

    /**
     * Publicado → borrador para preparar una nueva versión publicada del mismo expediente.
     */
    public function startNewRevisionCycle(string $documentId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->status !== 'published') {
            throw ValidationException::withMessages([
                'status' => ['Solo un documento publicado puede pasar a borrador para una nueva versión.'],
            ]);
        }

        return $this->documentStateService->transition($documentId, 'draft', $actorId, [
            // Igual que en plantillas: quien abre la nueva versión pasa a ser el
            // editor titular del ciclo en curso, evitando dobles editores en borrador.
            'owner_id' => $actorId,
            'created_by' => $actorId,
        ]);
    }

    public function applyTemplateMigration(ApplyTemplateMigrationDto $dto): Document
    {
        return $this->documentRepository->transaction(function () use ($dto): Document {
            $document = $this->documentRepository->findOrFail($dto->documentId);

            if ($document->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => ['El documento debe estar en borrador (nueva versión) para migrar de plantilla.'],
                ]);
            }

            $target = $this->entityVersionRepository->findPublishedByIdForVersionable(
                $dto->targetTemplateVersionId,
                Template::class,
                (string) $document->template_id,
            );
            if ($target === null) {
                throw ValidationException::withMessages([
                    'target_template_version_id' => ['La versión de plantilla destino no existe o no es publicada.'],
                ]);
            }

            $current = $this->resolveCurrentPublishedTemplateVersionMeta($document);
            if ($current !== null && (int) $target->version_number <= (int) $current['version_number']) {
                throw ValidationException::withMessages([
                    'target_template_version_id' => ['La versión de plantilla destino debe ser más reciente que la actual.'],
                ]);
            }

            $targetDefinitions = $this->documentBlockService
                ->templatePublicationDefinitionRowsFromEntityVersion((string) $target->id);
            if ($targetDefinitions === []) {
                throw ValidationException::withMessages([
                    'target_template_version_id' => ['La versión de plantilla destino no contiene bloques.'],
                ]);
            }

            $this->reconcileDocumentBlocks(
                (string) $document->id,
                $targetDefinitions,
                $dto->migratedBlockContent,
                $dto->removedBlockActions,
                $dto->actorId,
            );

            // Re-anclar tras reconciliar (la columna no es atributo delegado del head).
            $this->documentRepository->updateTemplateVersionAnchor(
                (string) $document->id,
                (string) $target->id,
            );

            return $this->documentRepository->findOrFailForRefreshAfterMutation($dto->documentId);
        });
    }

    /**
     * Reconcilia los bloques del documento con las definiciones de la versión destino:
     * crea los nuevos, aplica contenido migrado (salvo locked) en los existentes, y
     * elimina/mantiene los removidos según {@code $removedActions}.
     *
     * @param  list<array<string, mixed>>  $targetDefinitions
     * @param  array<string, mixed>  $migrated
     * @param  array<string, string>  $removedActions
     */
    private function reconcileDocumentBlocks(
        string $documentId,
        array $targetDefinitions,
        array $migrated,
        array $removedActions,
        string $actorId,
    ): void {
        $existing = $this->documentBlockRepository->listByDocumentKeyedByTemplateBlock($documentId);
        $targetIds = [];

        foreach ($targetDefinitions as $def) {
            $templateBlockId = (string) ($def['id'] ?? '');
            if ($templateBlockId === '') {
                continue;
            }
            $targetIds[$templateBlockId] = true;

            $state = (string) ($def['block_state'] ?? 'editable');
            $row = $existing->get($templateBlockId);
            $hasMigrated = $state !== 'locked' && array_key_exists($templateBlockId, $migrated);

            if ($row !== null) {
                if ($hasMigrated) {
                    $this->documentBlockRepository->updateBlock($row, $migrated[$templateBlockId], true, $actorId);
                }

                continue;
            }

            // Bloque nuevo en la versión destino: lo instanciamos.
            $content = $state === 'editable' ? null : ($def['default_content'] ?? null);
            if ($hasMigrated) {
                $content = $migrated[$templateBlockId];
            }

            $this->documentBlockRepository->insertDocumentBlock([
                'document_id' => $documentId,
                'template_block_id' => $templateBlockId,
                'content' => $content,
                'sort_order' => (int) ($def['sort_order'] ?? 0),
                'is_filled' => $content !== null,
                'last_edited_by' => $content !== null ? $actorId : null,
            ]);
        }

        foreach ($existing as $templateBlockId => $row) {
            if (isset($targetIds[(string) $templateBlockId])) {
                continue;
            }
            // Removido en la versión destino: eliminar o mantener según elección.
            if (($removedActions[(string) $templateBlockId] ?? null) === 'delete') {
                $this->documentBlockRepository->deleteBlock($row);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeUpdateAttributesAgainstDocumentAndTemplate(Document $document, array $attributes): array
    {
        $templateMeta = $this->resolveTemplateMetaForDocument($document);
        if ($templateMeta === null) {
            return $attributes;
        }

        $visibility = $templateMeta['visibility_level'] ?? null;
        if (! is_string($visibility) || $visibility === '') {
            return $attributes;
        }

        $visibilityLevel = TemplateVisibilityLevel::from($visibility);

        $ctx = new AcademicScopeContext(
            visibilityLevel: $visibilityLevel,
            templateStudyTypeId: isset($templateMeta['study_type_id']) && is_string($templateMeta['study_type_id']) && $templateMeta['study_type_id'] !== ''
                ? $templateMeta['study_type_id']
                : null,
            templateStudyId: isset($templateMeta['study_id']) && is_string($templateMeta['study_id']) && $templateMeta['study_id'] !== ''
                ? $templateMeta['study_id']
                : null,
            templateModuleId: isset($templateMeta['module_id']) && is_string($templateMeta['module_id']) && $templateMeta['module_id'] !== ''
                ? $templateMeta['module_id']
                : null,
            entityStudyTypeId: is_string($document->study_type_id) && $document->study_type_id !== '' ? $document->study_type_id : null,
            entityStudyId: is_string($document->study_id) && $document->study_id !== '' ? $document->study_id : null,
            entityModuleId: is_string($document->module_id) && $document->module_id !== '' ? $document->module_id : null,
            onModuleConflict: 'El documento debe mantenerse en el mismo módulo de la plantilla.',
            onStudyConflict: 'El documento debe mantenerse en el mismo estudio de la plantilla.',
            onModuleStudyMismatch: 'El módulo debe pertenecer al mismo estudio de la plantilla.',
            onModuleTypeMismatch: 'El módulo debe pertenecer a un estudio del mismo tipo que la plantilla.',
            onStudyTypeMismatch: 'El estudio debe pertenecer al mismo tipo de estudio de la plantilla.',
            onModuleNotFound: 'El módulo seleccionado no existe.',
            onStudyModuleMismatch: 'El estudio indicado no corresponde con el módulo seleccionado.',
            strictTemplateIds: false,
        );

        return AcademicScopeNormalizer::normalize($this->academicHierarchyRepository, $ctx, $attributes);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTemplateMetaForDocument(Document $document): ?array
    {
        $templateId = (string) $document->template_id;
        if ($templateId === '') {
            return null;
        }

        $templateVersionId = $document->template_version_id;
        if (is_string($templateVersionId) && $templateVersionId !== '') {
            $entityVersion = $this->entityVersionRepository->findPublishedByIdForVersionable(
                $templateVersionId,
                Template::class,
                $templateId,
            );
            if ($entityVersion !== null && is_array($entityVersion->snapshot_data)) {
                $templateMeta = $entityVersion->snapshot_data['template'] ?? null;
                if (is_array($templateMeta)) {
                    return $templateMeta;
                }
            }
        }

        $latestPublished = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
        if ($latestPublished === null || ! is_array($latestPublished->snapshot_data)) {
            return null;
        }

        $templateMeta = $latestPublished->snapshot_data['template'] ?? null;

        return is_array($templateMeta) ? $templateMeta : null;
    }

    private function clearPastDeliveryDeadline(mixed $deadline): mixed
    {
        return CloneDeadlinePolicy::clearIfPast($deadline);
    }

    private function assertDocumentMetadataInvariantsForMutation(string $title, mixed $deliveryDeadline): void
    {
        if (trim($title) === '') {
            throw ValidationException::withMessages([
                'title' => ['El título del documento es obligatorio.'],
            ]);
        }

        if ($deliveryDeadline === null || (is_string($deliveryDeadline) && trim($deliveryDeadline) === '')) {
            throw ValidationException::withMessages([
                'delivery_deadline' => ['La fecha de entrega del documento es obligatoria.'],
            ]);
        }
    }

    /**
     * Descarta la versión de trabajo del documento y restaura la última publicación.
     */
    public function destroyVersion(string $documentId, string $versionId, string $actorId): Document
    {
        return $this->documentRepository->transaction(function () use ($documentId, $versionId) {
            $document = $this->documentRepository->findOrFail($documentId);
            $document->loadMissing('headVersion');
            $head = $document->headVersion;

            // Shared guards + entity_version reset (delegated to EntityVersionDestroyService).
            $publishedSnapshot = $this->entityVersionDestroyService->assertAndResetToPublished(
                entityClass: Document::class,
                entityId: $documentId,
                targetVersionId: $versionId,
                head: $head,
                notCurrentMessage: 'Solo se puede descartar la versión de trabajo actual del documento.',
                statusMessage: 'Solo se pueden descartar versiones no publicadas (draft/in_review/rejected).',
                noPublishedMessage: 'No existe una versión publicada a la que restaurar.',
            );

            $publishedBlocks = isset($publishedSnapshot['blocks']) && is_array($publishedSnapshot['blocks'])
                ? $publishedSnapshot['blocks']
                : [];

            // Domain-specific restoration (blocks + reviews + status sync) remains here.
            $this->restorePublishedDocumentBlocks($documentId, $publishedBlocks);
            $this->documentRepository->deleteReviewsForDocument($documentId);

            // Re-sincroniza estado de cabecera delegado tras restaurar snapshot.
            $this->documentRepository->mergeHeadWorkingCopy($document, [
                'status' => 'published',
            ]);

            return $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
        });
    }

    /**
     * Restaura los bloques de un documento desde una versión publicada.
     *
     * @param  list<array<string, mixed>>  $publishedBlocks
     */
    private function restorePublishedDocumentBlocks(string $documentId, array $publishedBlocks): void
    {
        $existingByTemplateBlock = $this->documentBlockRepository
            ->listByDocumentKeyedByTemplateBlock($documentId)
            ->filter(fn (DocumentBlock $block): bool => is_string($block->template_block_id) && $block->template_block_id !== '');

        $seenTemplateBlockIds = [];
        foreach ($publishedBlocks as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $templateBlockId = isset($row['template_block_id']) && is_string($row['template_block_id']) && $row['template_block_id'] !== ''
                ? $row['template_block_id']
                : null;
            if ($templateBlockId === null) {
                continue;
            }

            $seenTemplateBlockIds[] = $templateBlockId;

            $payload = [
                'content' => array_key_exists('content', $row) ? $row['content'] : null,
                'is_filled' => (bool) ($row['is_filled'] ?? false),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $index,
                'last_edited_by' => isset($row['last_edited_by']) && is_string($row['last_edited_by']) ? $row['last_edited_by'] : null,
                'locked_by' => isset($row['locked_by']) && is_string($row['locked_by']) ? $row['locked_by'] : null,
                'locked_at' => isset($row['locked_at']) && is_string($row['locked_at']) && trim($row['locked_at']) !== '' ? $row['locked_at'] : null,
            ];

            /** @var DocumentBlock|null $existing */
            $existing = $existingByTemplateBlock->get($templateBlockId);
            if ($existing !== null) {
                $this->documentBlockRepository->updateBlockAttributes($existing, $payload);

                continue;
            }

            $this->documentBlockRepository->create([
                'id' => (string) Str::uuid(),
                'document_id' => $documentId,
                'template_block_id' => $templateBlockId,
                ...$payload,
            ]);
        }

        if ($seenTemplateBlockIds === []) {
            $this->documentBlockRepository->deleteAllForDocument($documentId);

            return;
        }

        $this->documentBlockRepository->deleteForDocumentExceptTemplateBlocks($documentId, $seenTemplateBlockIds);
    }

    /**
     * Envia el documento a revisión.
     */
    public function submitToReview(string $documentId, string $actorId, string $changelog): Document
    {
        $normalizedChangelog = VersionSubmissionChangelog::normalize($changelog);
        $document = $this->documentRepository->findOrFail($documentId);

        if (! in_array($document->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Solo los documentos en borrador o rechazados pueden enviarse a revisión.'],
            ]);
        }

        $this->documentBlockService->assertMandatoryBlocksAreFilled($documentId);

        return $this->documentRepository->transaction(function () use ($documentId, $actorId, $document, $normalizedChangelog) {
            $this->documentRepository->deleteReviewsForDocument($documentId);
            $this->documentRepository->updateHeadVersionChangelog($documentId, $normalizedChangelog);

            $candidates = $this->resolveReviewCandidatesFromTemplateVersion($document);

            if ($candidates === []) {
                // No resolvable reviewer pool for this document → auto-publish regardless of
                // visibility level. The template may simply have no validators configured, which
                // is a valid state (e.g. personal use, or a coordinator who skipped step 3).
                $this->documentStateService->transition($documentId, 'published', $actorId);

                $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                    documentId: $documentId,
                    triggerEvent: 'published',
                    triggeredBy: $actorId,
                    notes: $normalizedChangelog,
                ));
                $this->documentRepository->clearHeadVersionChangelog($documentId);

                $autoPublished = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);

                $recipientId = is_string($autoPublished->owner_id) && $autoPublished->owner_id !== ''
                    ? $autoPublished->owner_id
                    : (is_string($autoPublished->created_by) ? $autoPublished->created_by : null);

                if ($recipientId !== null && $recipientId !== '') {
                    try {
                        $this->notificationPublisher->send(
                            type: 'document.published',
                            recipientId: $recipientId,
                            title: 'Documento publicado',
                            body: 'El documento "'.$autoPublished->title.'" ha sido publicado correctamente',
                            titleKey: 'notifications.document.published.title',
                            bodyKey: 'notifications.document.published.body',
                            params: ['document_id' => (string) $autoPublished->id, 'document_title' => $autoPublished->title],
                            severity: 'info',
                            channels: ['app'],
                            metadata: ['document_id' => (string) $autoPublished->id],
                        );
                    } catch (\Throwable $e) {
                        Log::warning('notification.publish_failed', [
                            'error' => $e->getMessage(),
                            'type' => 'document.published',
                            'document_id' => (string) $autoPublished->id,
                        ]);
                    }
                }

                return $autoPublished;
            }

            $document->load(['blocks' => fn ($q) => $q->orderBy('sort_order')]);
            $blocksSnapshot = $document->blocks->map(fn ($b) => [
                'document_block_id' => (string) $b->id,
                'template_block_id' => (string) $b->template_block_id,
                'sort_order' => (int) $b->sort_order,
                'content' => $b->content,
            ])->values()->all();

            $headVersion = $document->headVersion;
            if ($headVersion !== null) {
                $cycles = is_array($headVersion->change_set) ? $headVersion->change_set : [];
                $cycles[] = [
                    'cycle' => count($cycles) + 1,
                    'submitted_at' => now()->toIso8601String(),
                    'submitted_by' => $actorId,
                    'blocks' => $blocksSnapshot,
                ];
                $this->entityVersionRepository->update($headVersion, ['change_set' => $cycles]);
            }

            $reviewMode = $this->documentReviewModeResolver->resolve($document);
            $document = $this->documentStateService->transition($documentId, 'in_review', $actorId, ['review_mode' => $reviewMode]);
            $this->documentRepository->createPendingReviews($documentId, $candidates);

            $this->notifyReviewersOfValidationRequest($document, $candidates, $reviewMode);

            DocumentSubmittedForReview::dispatch(
                $documentId,
                $actorId,
                $reviewMode,
                array_map(fn (array $c) => [
                    'id' => $c['reviewer_id'],
                    'name' => $this->userDirectoryRepository->findNameById($c['reviewer_id']),
                    'stage' => $c['stage'],
                ], $candidates),
                $document->title,
                $document->study_type_id,
                $document->study_id,
                $document->module_id,
                $normalizedChangelog,
            );

            return $document;
        });
    }

    /**
     * Notifica a los revisores asignados que hay un documento pendiente de revisión.
     *
     * @param  list<array{reviewer_id: string, stage: int}>  $candidates
     */
    private function notifyReviewersOfValidationRequest(Document $document, array $candidates, string $reviewMode): void
    {
        $recipients = ReviewValidationNotificationRecipients::filterForReviewMode(
            $reviewMode,
            $candidates,
        );

        foreach ($recipients as $candidate) {
            $reviewerId = $candidate['reviewer_id'] ?? '';
            if (! is_string($reviewerId) || $reviewerId === '') {
                continue;
            }

            try {
                $this->notificationPublisher->send(
                    type: 'document.validation_requested',
                    recipientId: $reviewerId,
                    title: 'Nueva solicitud de revisión',
                    body: 'El documento "'.$document->title.'" requiere tu revisión',
                    titleKey: 'notifications.document.validation_requested.title',
                    bodyKey: 'notifications.document.validation_requested.body',
                    params: ['document_id' => (string) $document->id, 'document_title' => $document->title],
                    severity: 'high',
                    channels: ['app'],
                    metadata: ['document_id' => (string) $document->id],
                );
            } catch (\Throwable $e) {
                Log::warning('notification.publish_failed', [
                    'error' => $e->getMessage(),
                    'type' => 'document.validation_requested',
                    'document_id' => (string) $document->id,
                    'reviewer_id' => $reviewerId,
                ]);
            }
        }
    }

    /**
     * Resuelve candidatos de revisión desde la versión de plantilla anclada al documento.
     *
     * @return list<array{reviewer_id: string, stage: int}>
     */
    private function resolveReviewCandidatesFromTemplateVersion(Document $document): array
    {
        $versionId = $document->template_version_id;
        if (! is_string($versionId) || $versionId === '') {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $entityVersion = $this->entityVersionRepository->findPublishedByIdForVersionable(
            $versionId,
            Template::class,
            (string) $document->template_id,
        );

        if ($entityVersion === null || ! is_array($entityVersion->snapshot_data)) {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $reviewersPayload = $entityVersion->snapshot_data['reviewers'] ?? null;
        if (! is_array($reviewersPayload)) {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $documentReviewers = $reviewersPayload['document_reviewers'] ?? [];
        if (! is_array($documentReviewers) || $documentReviewers === []) {
            return [];
        }

        $candidates = [];
        $fallbackStage = 1;
        foreach ($documentReviewers as $row) {
            if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                continue;
            }
            $resolvedStage = $this->resolveDocumentReviewerStage($row['stage'] ?? null, $fallbackStage);
            $candidates[] = [
                'reviewer_id' => $row['user_id'],
                'stage' => $resolvedStage,
            ];
            $fallbackStage++;
        }

        return $candidates;
    }

    /**
     * @return list<array{reviewer_id: string, stage: int}>
     */
    private function resolveReviewCandidatesFromTemplateLiveConfig(Document $document): array
    {
        $template = $this->templateRepository
            ->findForDocumentReviewCandidatesWithoutCatalogScope((string) $document->template_id);

        if ($template === null || $template->documentReviewers->isEmpty()) {
            return [];
        }

        $candidates = [];
        $fallbackStage = 1;
        foreach ($template->documentReviewers as $dr) {
            $candidates[] = [
                'reviewer_id' => (string) $dr->user_id,
                'stage' => $this->resolveDocumentReviewerStage($dr->stage, $fallbackStage),
            ];
            $fallbackStage++;
        }

        return $candidates;
    }

    /**
     * Etapa desde fila persistida; fallback para snapshots legacy sin `stage`.
     */
    private function resolveDocumentReviewerStage(mixed $stageValue, int $fallbackStage): int
    {
        return is_numeric($stageValue) && (int) $stageValue > 0
            ? (int) $stageValue
            : $fallbackStage;
    }

    /**
     * Pool de validadores efectivo del documento para mostrar en el wizard.
     *
     * Los `document_reviewers` se resuelven con la MISMA fuente que el envío a
     * revisión ({@see self::resolveReviewCandidatesFromTemplateVersion}): la versión
     * publicada de plantilla anclada al documento (con fallback a la config viva).
     * Así la UI muestra exactamente los validadores que se materializarán al validar,
     * sin requerir acceso de lectura a la plantilla.
     *
     * Si la plantilla no define validadores de documento pero sí revisores de plantilla,
     * se devuelven estos últimos como información (`template_fallback`): no participan en
     * la validación del documento, que se publicará directamente.
     */
    public function getDocumentReviewerPool(Document $document): ReviewerPoolDto
    {
        $reviewMode = $this->documentReviewModeResolver->resolve($document);

        $candidates = $this->resolveReviewCandidatesFromTemplateVersion($document);
        if ($candidates !== []) {
            return new ReviewerPoolDto(
                kind: 'document',
                reviewMode: $reviewMode,
                reviewers: array_map(fn (array $c) => [
                    'id' => $c['reviewer_id'],
                    'name' => $this->userDirectoryRepository->findNameById($c['reviewer_id']),
                    'stage' => $c['stage'],
                ], $candidates),
            );
        }

        $templateReviewerIds = $this->resolveTemplateReviewerIdsFromAnchoredVersion($document);
        if ($templateReviewerIds !== []) {
            return new ReviewerPoolDto(
                kind: 'template_fallback',
                reviewMode: $reviewMode,
                reviewers: array_map(fn (string $id) => [
                    'id' => $id,
                    'name' => $this->userDirectoryRepository->findNameById($id),
                    'stage' => null,
                ], $templateReviewerIds),
            );
        }

        return new ReviewerPoolDto(
            kind: 'none',
            reviewMode: $reviewMode,
            reviewers: [],
        );
    }

    /**
     * IDs de revisores de plantilla (`template_reviewers`) del snapshot de la versión
     * publicada anclada al documento. Solo informativos para la UI.
     *
     * @return list<string>
     */
    private function resolveTemplateReviewerIdsFromAnchoredVersion(Document $document): array
    {
        $versionId = $document->template_version_id;
        if (! is_string($versionId) || $versionId === '') {
            return [];
        }

        $entityVersion = $this->entityVersionRepository->findPublishedByIdForVersionable(
            $versionId,
            Template::class,
            (string) $document->template_id,
        );

        if ($entityVersion === null || ! is_array($entityVersion->snapshot_data)) {
            return [];
        }

        $rows = data_get($entityVersion->snapshot_data, 'reviewers.template_reviewers');
        if (! is_array($rows)) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $userId = is_array($row) ? ($row['user_id'] ?? null) : null;
            if (is_string($userId) && $userId !== '') {
                $ids[] = $userId;
            }
        }

        return $ids;
    }

    /**
     * Publica el documento.
     */
    public function publishDocument(string $documentId, string $actorId, ?string $changelog): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if (! in_array($document->status, ['draft', 'in_review'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede publicar un documento en borrador o en revisión.'],
            ]);
        }

        if ($document->status === 'draft') {
            $candidates = $this->resolveReviewCandidatesFromTemplateVersion($document);
            if ($candidates !== []) {
                throw ValidationException::withMessages([
                    'reviews' => ['El documento tiene validadores asignados. Debe completar la revisión para publicarse.'],
                ]);
            }

            $this->documentBlockService->assertMandatoryBlocksAreFilled((string) $document->id);
        }

        if ($document->status === 'in_review' && $this->documentRepository->countPendingReviewsForDocument($documentId) > 0) {
            throw ValidationException::withMessages([
                'reviews' => ['Quedan revisiones pendientes.'],
            ]);
        }

        return $this->documentRepository->transaction(function () use ($documentId, $actorId, $changelog) {
            $document = $this->documentRepository->findOrFail($documentId);
            $document->loadMissing('headVersion');
            $resolvedChangelog = VersionSubmissionChangelog::requireNonEmpty(
                $changelog,
                $document->headVersion?->changelog,
            );

            $this->documentStateService->transition($documentId, 'published', $actorId);
            $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                documentId: $documentId,
                triggerEvent: 'published',
                triggeredBy: $actorId,
                notes: $resolvedChangelog,
            ));
            $this->documentRepository->clearHeadVersionChangelog($documentId);

            $refreshed = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);

            $recipientId = is_string($refreshed->owner_id) && $refreshed->owner_id !== ''
                ? $refreshed->owner_id
                : (is_string($refreshed->created_by) ? $refreshed->created_by : null);

            if ($recipientId !== null && $recipientId !== '') {
                try {
                    $this->notificationPublisher->send(
                        type: 'document.published',
                        recipientId: $recipientId,
                        title: 'Documento publicado',
                        body: 'El documento "'.$refreshed->title.'" ha sido publicado correctamente',
                        titleKey: 'notifications.document.published.title',
                        bodyKey: 'notifications.document.published.body',
                        params: ['document_id' => (string) $refreshed->id, 'document_title' => $refreshed->title],
                        severity: 'info',
                        channels: ['app'],
                        metadata: ['document_id' => (string) $refreshed->id],
                    );
                } catch (\Throwable $e) {
                    Log::warning('notification.publish_failed', [
                        'error' => $e->getMessage(),
                        'type' => 'document.published',
                        'document_id' => (string) $refreshed->id,
                    ]);
                }
            }

            return $refreshed;
        });
    }

    /**
     * Delega la propiedad del documento a otro usuario.
     * Solo el titular actual puede delegar; esta invariante se refuerza aquí
     * además de en la policy del controlador, para proteger llamadas desde otros entrypoints.
     */
    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->owner_id !== $actorId) {
            throw new AuthorizationException('Solo el titular puede delegar la titularidad del documento.');
        }

        if ($newOwnerId === $document->owner_id) {
            throw ValidationException::withMessages([
                'new_owner_id' => ['El nuevo titular debe ser distinto del actual.'],
            ]);
        }

        $previousOwnerId = (string) $document->owner_id;
        $updated = $this->documentRepository->updateOwner($document, $newOwnerId);

        $request = request();
        OwnershipTransferred::dispatch(
            'document',
            (string) $updated->getKey(),
            $previousOwnerId,
            $newOwnerId,
            $actorId,
            $this->userDirectoryRepository->findNameById($previousOwnerId),
            $this->userDirectoryRepository->findNameById($newOwnerId),
            $request?->ip(),
            $request?->userAgent(),
        );

        return $updated;
    }

    /**
     * Lista las revisiones del documento.
     *
     * @return Collection<int, DocumentReview>
     */
    public function listReviews(string $documentId): Collection
    {
        return $this->documentReviewService->listReviews($documentId);
    }

    /**
     * Aprueba una revisión del documento.
     */
    public function approveReview(string $documentId, string $reviewId, string $actorId, ?string $publicationChangelog = null): Document
    {
        return $this->documentReviewService->approveReview($documentId, $reviewId, $actorId, $publicationChangelog);
    }

    /**
     * Rechaza una revisión del documento.
     */
    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): Document
    {
        return $this->documentReviewService->rejectReview($documentId, $reviewId, $actorId, $reason);
    }

    /**
     * Localiza una versión de documento por su ID.
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersionDto
    {
        return $this->documentVersionService->findDocumentVersionOrFail($documentId, $versionId);
    }

    /**
     * Detalle de versión del documento aceptando id legacy o id polimórfico.
     */
    public function findDocumentVersionDetailOrFail(string $documentId, string $versionId): DocumentVersionDetailDto
    {
        return $this->documentVersionService->findDocumentVersionDetailOrFail($documentId, $versionId);
    }

    /**
     * Metadatos de versiones del documento (sin snapshot completo).
     *
     * @return list<DocumentVersionSummaryDto>
     */
    public function listDocumentVersions(string $documentId): array
    {
        return $this->documentVersionService->listDocumentVersions($documentId);
    }

    public function attachLatestPublishedVersionMeta(Collection $documents): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $ids = $documents->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->values()->all();
        if ($ids === []) {
            return;
        }

        $latestByDocument = $this->entityVersionRepository->findLatestPublishedRowsByVersionables(
            Document::class,
            $ids,
        );

        foreach ($documents as $document) {
            $meta = $latestByDocument[(string) $document->id] ?? null;
            $document->setAttribute('latest_published_version_id', $meta['id'] ?? null);
            $document->setAttribute('latest_published_version_number', $meta['version_number'] ?? null);
            $document->setAttribute(
                'latest_published_title',
                $meta !== null ? $this->extractPublishedTitleFromSnapshot($meta['snapshot_data']) : null,
            );
        }
    }

    public function attachTemplateVersionNumbers(Collection $documents): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $versionIds = $documents
            ->pluck('template_version_id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
        if ($versionIds === []) {
            return;
        }

        $versionNumberById = $this->entityVersionRepository->findVersionNumbersByIds($versionIds);

        foreach ($documents as $document) {
            $templateVersionId = $document->template_version_id;
            if (! is_string($templateVersionId) || $templateVersionId === '') {
                continue;
            }
            if (array_key_exists($templateVersionId, $versionNumberById)) {
                $document->setAttribute('template_version_number', $versionNumberById[$templateVersionId]);
            }
        }
    }

    public function attachIsAssignedReviewerMeta(Collection $documents, string $viewerId): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $ids = $documents->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->values()->all();
        if ($ids === []) {
            return;
        }

        $assignedDocIds = array_flip(
            $this->documentRepository->findAssignedReviewerDocumentIds($ids, $viewerId),
        );

        foreach ($documents as $document) {
            $document->setAttribute('is_assigned_reviewer', array_key_exists((string) $document->id, $assignedDocIds));
        }
    }

    /**
     * Resuelve el contexto de visibilidad para el endpoint `show` de Document.
     *
     * Determina si el viewer debe recibir el snapshot publicado o el contenido vivo,
     * y si es revisor asignado activo. Encapsula la lógica de branching que antes
     * vivía directamente en DocumentController::show().
     *
     * @return array{serve_published_snapshot: bool, is_assigned_reviewer: bool}
     */
    public function resolveDocumentViewerContext(Document $resolved, string $documentId, string $viewerId): array
    {
        $servePublishedSnapshot = false;

        try {
            $this->documentRepository->findOrFail($documentId);
        } catch (ModelNotFoundException) {
            $servePublishedSnapshot = true;
        }

        // Titular efectivo: si hay titular operativo (owner_id) solo cuenta ese; si no,
        // el autor (created_by) como fallback. Tras una cesión, el autor anterior deja de
        // tratarse como titular y recibe el snapshot publicado en lugar del contenido vivo.
        $ownerId = (string) $resolved->owner_id;
        $isCreator = $ownerId !== ''
            ? $viewerId === $ownerId
            : $viewerId === (string) $resolved->created_by;
        $isAssignedReviewer = false;

        if (! $servePublishedSnapshot && ! $isCreator && in_array($resolved->status, ['draft', 'in_review'], true)) {
            $isAssignedReviewer = $resolved->status === 'in_review'
                && $this->documentRepository->isReviewerAssignedToDocument($documentId, $viewerId);

            if (! $isAssignedReviewer) {
                $servePublishedSnapshot = true;
            }
        } elseif (! $servePublishedSnapshot && $resolved->status === 'in_review') {
            $isAssignedReviewer = $this->documentRepository->isReviewerAssignedToDocument($documentId, $viewerId);
        }

        return [
            'serve_published_snapshot' => $servePublishedSnapshot,
            'is_assigned_reviewer' => $isAssignedReviewer,
        ];
    }

    private function extractPublishedTitleFromSnapshot(mixed $snapshot): ?string
    {
        if (is_string($snapshot) && $snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }
        if (! is_array($snapshot)) {
            return null;
        }
        $title = data_get($snapshot, 'document.title');
        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        return $title;
    }

    /**
     * Obtiene el nombre del usuario propietario del documento.
     * Devuelve un nombre por defecto si el usuario no existe.
     */
    public function getOwnerNameForDocument(string $documentId): string
    {
        $document = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);

        if ($document->owner_id === null) {
            return 'otro usuario';
        }

        $ownerName = $this->userDirectoryRepository->findNameById($document->owner_id);

        return $ownerName ?? 'otro usuario';
    }

    public function resolveWorkingRevisionConflict(Document $document): WorkingRevisionConflictDto
    {
        $document->loadMissing(['headVersion', 'owner']);
        $editorName = $document->owner?->name;
        if (($editorName === null || $editorName === '') && $document->owner_id !== null) {
            $editorName = $this->userDirectoryRepository->findNameById($document->owner_id);
        }

        return WorkingRevisionConflictResolver::resolve(
            (string) $document->status,
            $this->findLatestPublishedVersion($document->id),
            $document->headVersion,
            is_string($editorName) && $editorName !== '' ? $editorName : null,
        );
    }

    public function attachWorkingRevisionPresentationMeta(Document $document): void
    {
        WorkingRevisionConflictResolver::attachToModel(
            $document,
            $this->resolveWorkingRevisionConflict($document),
        );
    }

    /**
     * Prepara un documento para visualización, adjuntando relaciones y metadatos derivados.
     * Centraliza la carga de relaciones y cálculo de metadatos que antes estaban dispersos
     * en el controller.
     */
    public function prepareDocumentForDisplay(
        Document $document,
        ?EntityVersion $latestPublished = null,
        bool $isAssignedReviewer = false,
    ): void {
        // Cargar propietario si no está cargado
        $document->loadMissing(['owner']);

        // Si se proporciona última versión publicada, establecerla como relación
        if ($latestPublished !== null) {
            $document->setRelation('headVersion', $latestPublished);
        }

        // Determinar si hay comentarios visibles para el usuario sobre este documento.
        $hasReviewComments = $this->commentRepository->existsForCommentable(
            Document::class,
            (string) $document->getKey(),
        );
        $document->setAttribute('has_review_comments', $hasReviewComments);

        // Establecer si es revisor asignado
        $document->setAttribute('is_assigned_reviewer', $isAssignedReviewer);
    }
}
