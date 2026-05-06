<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentVersion;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Support\PublishedTemplateVersionMetaMerge;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
        private readonly SnapshotServiceInterface $snapshotService,
        private readonly DocumentBlockService $documentBlockService,
        private readonly DocumentVersionService $documentVersionService,
        private readonly DocumentShareService $documentShareService,
        private readonly DocumentStateService $documentStateService,
        private readonly DocumentReviewService $documentReviewService,
        private readonly EntityVersionLifecycleServiceInterface $entityVersionLifecycleService,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    /**
     * Localiza un documento por su ID.
     */
    public function findOrFail(string $id): Document
    {
        return $this->documentRepository->findOrFail($id);
    }

    /**
     * Crea un documento a partir de un DTO.
     */
    public function create(CreateDocumentDto $dto): Document
    {
        $this->templateRepository->findOrFail($dto->templateId);

        if ($dto->templateVersionId !== null) {
            $version = $this->templateVersionRepository->findOrFail($dto->templateVersionId);
            if ($version->template_id !== $dto->templateId) {
                throw ValidationException::withMessages([
                    'template_version_id' => ['La versión no pertenece a la plantilla indicada.'],
                ]);
            }
        } else {
            $version = $this->resolveTemplateVersionRowForNewDocument($dto->templateId);
        }

        $snapshot = $version->blocks_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            throw ValidationException::withMessages([
                'template_id' => ['La versión de plantilla no contiene bloques.'],
            ]);
        }

        $blockRows = collect($snapshot)
            ->sortBy(fn ($b) => $b['sort_order'] ?? 0)
            ->map(fn (array $b) => [
                'template_block_id' => (string) $b['id'],
                'content' => $b['default_content'] ?? null,
                'sort_order' => (int) ($b['sort_order'] ?? 0),
            ])
            ->values()
            ->all();

        return $this->documentRepository->createDocumentWithBlocks([
            'process_id' => $dto->processId,
            'template_id' => $dto->templateId,
            'template_version_id' => $version->id,
            'title' => $dto->title,
            'study_type_id' => $dto->studyTypeId,
            'study_id' => $dto->studyId,
            'module_id' => $dto->moduleId,
            'delivery_deadline' => $dto->deliveryDeadline,
            'created_by' => $dto->createdBy,
            'owner_id' => $dto->ownerId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ], $blockRows);
    }

    /**
     * Clona un documento origen hacia uno nuevo en borrador.
     *
     * Si existe al menos una versión publicada en {@see DocumentVersion}, el borrador copiado se materializa desde el
     * último snapshot con trigger_event «published» (no desde los bloques vivos del documento).
     */
    public function clone(string $sourceDocumentId, string $actorId): Document
    {
        return $this->documentRepository->transaction(function () use ($sourceDocumentId, $actorId) {
            $source = $this->documentRepository->findOrFail($sourceDocumentId);

            $publishedSnapshot = $this->documentRepository->findLatestPublishedDocumentVersion($sourceDocumentId);

            if ($publishedSnapshot !== null && is_array($publishedSnapshot->snapshot_data)) {
                $snap = $publishedSnapshot->snapshot_data;
                $docSnap = isset($snap['document']) && is_array($snap['document']) ? $snap['document'] : [];
                $blockSnapshots = isset($snap['blocks']) && is_array($snap['blocks']) ? $snap['blocks'] : [];
                $blockRows = $this->cloneBlockRowsFromSnapshotBlocks($blockSnapshots, $actorId);

                if ($blockRows !== []) {
                    return $this->documentRepository->createDocumentWithBlocks(
                        $this->cloneDocumentAttributesFromPublishedSnapshot($source, $docSnap, $actorId),
                        $blockRows,
                    );
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

            return $this->documentRepository->createDocumentWithBlocks([
                'process_id' => $source->process_id,
                'template_id' => $source->template_id,
                'template_version_id' => $source->template_version_id,
                'title' => $source->title.' (copia)',
                'study_type_id' => $source->study_type_id,
                'study_id' => $source->study_id,
                'module_id' => $source->module_id,
                'delivery_deadline' => $source->delivery_deadline,
                'created_by' => $actorId,
                'owner_id' => $actorId,
                'status' => 'draft',
                'current_version' => 1,
                'submitted_at' => null,
                'published_at' => null,
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
        $templateId = isset($docSnap['template_id']) && is_string($docSnap['template_id']) && $docSnap['template_id'] !== ''
            ? $docSnap['template_id']
            : (string) $source->template_id;

        $templateVersionId = $docSnap['template_version_id'] ?? $source->template_version_id;
        if ($templateVersionId !== null && ! is_string($templateVersionId)) {
            $templateVersionId = $source->template_version_id;
        }

        $titleBase = isset($docSnap['title']) && is_string($docSnap['title'])
            ? $docSnap['title']
            : (string) $source->title;

        return [
            'process_id' => $source->process_id,
            'template_id' => $templateId,
            'template_version_id' => $templateVersionId,
            'title' => $titleBase.' (copia)',
            'study_type_id' => array_key_exists('study_type_id', $docSnap) ? $docSnap['study_type_id'] : $source->study_type_id,
            'study_id' => array_key_exists('study_id', $docSnap) ? $docSnap['study_id'] : $source->study_id,
            'module_id' => array_key_exists('module_id', $docSnap) ? $docSnap['module_id'] : $source->module_id,
            'delivery_deadline' => $source->delivery_deadline,
            'created_by' => $actorId,
            'owner_id' => $actorId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ];
    }

    /**
     * Actualiza metadatos editables del documento.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $documentId, array $attributes): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        return $this->documentRepository->updateDocumentMetadata($document, $attributes);
    }

    /**
     * Borrado lógico del documento.
     */
    public function delete(string $documentId): void
    {
        $document = $this->documentRepository->findOrFail($documentId);
        $this->documentRepository->delete($document);
    }

    /**
     * Opciones de creación de documento disponibles para un módulo.
     * 
     * @return list<array{template_id: string, template_version_id: string, process_id: string, name: string, description: ?string}>
     */
    public function creationOptionsForModule(string $moduleId): array
    {
        $templates = $this->templateRepository->listPublishedByModule($moduleId);

        $options = [];
        foreach ($templates as $template) {
            $targetNumber = $this->resolveEffectivePublishedTemplateVersionNumber((string) $template->id);
            if ($targetNumber === null) {
                continue;
            }

            $version = $this->templateVersionRepository->findByTemplateIdAndVersionNumber((string) $template->id, $targetNumber);
            if ($version === null) {
                continue;
            }

            $options[] = [
                'template_id' => $template->id,
                'template_version_id' => $version->id,
                'process_id' => (string) $template->process_id,
                'name' => (string) $template->name,
                'description' => $template->description,
            ];
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
    ): Document
    {
        $options = $this->creationOptionsForModule($moduleId);
        if ($options === []) {
            throw ValidationException::withMessages([
                'module_id' => ['El módulo no tiene plantillas publicadas disponibles.'],
            ]);
        }

        $selected = null;
        if ($templateVersionId !== null) {
            foreach ($options as $option) {
                if ($option['template_version_id'] === $templateVersionId) {
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

        if ($selected['process_id'] !== $processId) {
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
            templateId: $selected['template_id'],
            title: 'Nueva Programación Didáctica',
            createdBy: $creatorId,
            ownerId: $creatorId,
            processId: $processId,
            studyTypeId: $moduleContext['study_type_id'],
            studyId: $moduleContext['study_id'],
            moduleId: $moduleContext['module_id'],
            deliveryDeadline: $deliveryDeadline,
            templateVersionId: $selected['template_version_id'],
        ));
    }

    /**
     * Comparación ligera entre la versión de plantilla anclada al documento y la última publicada.
     *
     * @return array{
     *   current_version: ?array{id: string, version_number: int},
     *   latest_version: ?array{id: string, version_number: int, changelog: string},
     *   has_update: bool,
     *   changelog: ?string
     * }
     */
    public function templateVersionStatus(string $documentId): array
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

        return [
            'current_version' => $current,
            'latest_version' => $latestFull,
            'has_update' => $hasUpdate,
            'changelog' => $hasUpdate ? $latestFull['changelog'] : null,
        ];
    }

    /**
     * Última versión publicada de plantilla: prioriza el mayor version_number entre entity_versions y template_versions;
     * si empatan, se prioriza entity_versions.
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    private function resolveLatestPublishedTemplateVersionMeta(string $templateId): ?array
    {
        $entityLatest = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
        $legacyLatest = $this->templateVersionRepository->findLatestPublishedMetaForTemplate($templateId);

        $entityMeta = $entityLatest === null ? null : [
            'id' => (string) $entityLatest->id,
            'version_number' => (int) $entityLatest->version_number,
            'changelog' => (string) ($entityLatest->changelog ?? ''),
        ];

        return PublishedTemplateVersionMetaMerge::preferLatestMeta($entityMeta, $legacyLatest);
    }

    private function resolveEffectivePublishedTemplateVersionNumber(string $templateId): ?int
    {
        $entityLatest = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
        $legacyLatest = $this->templateVersionRepository->findLatestPublishedForTemplate($templateId);

        return PublishedTemplateVersionMetaMerge::preferLatestVersionNumber(
            $entityLatest !== null ? (int) $entityLatest->version_number : null,
            $legacyLatest !== null ? (int) $legacyLatest->version_number : null,
        );
    }

    /**
     * Versión legacy ({@see TemplateVersion}) a usar al crear un documento sin {@see CreateDocumentDto::$templateVersionId}.
     * Exige fila en {@code template_versions} para el número efectivo (FK); si entity_versions va por delante sin fila legacy, error explícito.
     */
    private function resolveTemplateVersionRowForNewDocument(string $templateId): TemplateVersion
    {
        $targetNumber = $this->resolveEffectivePublishedTemplateVersionNumber($templateId);
        if ($targetNumber === null) {
            throw ValidationException::withMessages([
                'template_id' => ['La plantilla no tiene versiones publicadas; no se puede crear un documento.'],
            ]);
        }

        $version = $this->templateVersionRepository->findByTemplateIdAndVersionNumber($templateId, $targetNumber);
        if ($version === null) {
            throw ValidationException::withMessages([
                'template_id' => ['La plantilla tiene una versión publicada desincronizada respecto al historial clásico. Contacte con soporte o vuelva a publicar la plantilla.'],
            ]);
        }

        return $version;
    }

    /**
     * Versión de plantilla anclada al documento: template_versions primero, luego entity_versions.
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    private function resolveCurrentPublishedTemplateVersionMeta(Document $document): ?array
    {
        $versionId = $document->template_version_id;

        if (! is_string($versionId) || $versionId === '') {
            return null;
        }

        $legacy = $this->templateVersionRepository->findPublishedMetaById($versionId);
        if ($legacy !== null) {
            return $legacy;
        }

        return $this->entityVersionRepository->findPublishedMetaByIdForVersionable(
            $versionId,
            Template::class,
            (string) $document->template_id,
        );
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
     * @return list<array<string, mixed>>
     */
    public function blocksForDisplay(Document $document): array
    {
        return $this->documentBlockService->blocksForDisplay($document);
    }

    /**
     * Actualiza el contenido de un bloque de documento.
     *
     * @return array<string, mixed>
     */
    public function updateBlock(UpdateDocumentBlockDto $dto): array
    {
        return $this->documentBlockService->updateBlock($dto);
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
            'submitted_at' => null,
            'published_at' => null,
        ]);
    }

    /**
     * Envia el documento a revisión.
     */
    public function submitToReview(string $documentId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo los documentos en borrador pueden enviarse a revisión.'],
            ]);
        }

        $this->documentBlockService->assertMandatoryBlocksAreFilled($document);

        return $this->documentRepository->transaction(function () use ($documentId, $actorId, $document) {
            $this->documentRepository->deleteReviewsForDocument($documentId);

            $candidates = $this->resolveReviewCandidatesFromTemplateVersion($document);

            if ($candidates === []) {
                $this->documentStateService->transition($documentId, 'published', $actorId, [
                    'submitted_at' => now(),
                    'published_at' => now(),
                ]);

                $autoChangelog = 'Publicado automáticamente: no hay validadores configurados.';
                $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                    documentId: $documentId,
                    triggerEvent: 'published',
                    triggeredBy: $actorId,
                    notes: $autoChangelog,
                ));
                $latestVersion = $this->documentRepository->findLatestDocumentVersionOrFail($documentId);
                $this->entityVersionLifecycleService->createPublishedSnapshotVersion(
                    Document::class,
                    $documentId,
                    (int) $latestVersion->version_number,
                    is_array($latestVersion->snapshot_data) ? $latestVersion->snapshot_data : [],
                    $actorId,
                    $autoChangelog,
                );

                return $this->documentRepository->findOrFail($documentId);
            }

            $document = $this->documentStateService->transition($documentId, 'in_review', $actorId, [
                'submitted_at' => now(),
            ]);
            $this->documentRepository->createPendingReviews($documentId, $candidates);

            return $document;
        });
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

        $entityVersion = null;
        $templateVersionMeta = $this->templateVersionRepository->findPublishedMetaById($versionId);
        if ($templateVersionMeta !== null) {
            $entityVersion = $this->entityVersionRepository->findPublishedForEntityVersionNumber(
                Template::class,
                (string) $document->template_id,
                (int) $templateVersionMeta['version_number'],
            );
        } else {
            $entityVersion = $this->entityVersionRepository->findPublishedByIdForVersionable(
                $versionId,
                Template::class,
                (string) $document->template_id,
            );
        }

        if ($entityVersion === null || ! is_array($entityVersion->snapshot_data)) {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $reviewersPayload = $entityVersion->snapshot_data['reviewers'] ?? null;
        if (! is_array($reviewersPayload)) {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $documentReviewers = $reviewersPayload['document_reviewers'] ?? [];
        if (is_array($documentReviewers) && $documentReviewers !== []) {
            $candidates = [];
            $stage = 1;
            foreach ($documentReviewers as $row) {
                if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                    continue;
                }
                $candidates[] = [
                    'reviewer_id' => $row['user_id'],
                    'stage' => $stage,
                ];
                $stage++;
            }

            return $candidates;
        }

        $templateReviewers = $reviewersPayload['template_reviewers'] ?? [];
        if (! is_array($templateReviewers) || $templateReviewers === []) {
            return [];
        }

        $candidates = [];
        foreach ($templateReviewers as $row) {
            if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                continue;
            }

            $candidates[] = [
                'reviewer_id' => $row['user_id'],
                'stage' => isset($row['stage']) ? (int) $row['stage'] : 1,
            ];
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

        $candidates = [];
        if ($template !== null) {
            if ($template->documentReviewers->isNotEmpty()) {
                $stage = 1;
                foreach ($template->documentReviewers as $dr) {
                    $candidates[] = [
                        'reviewer_id' => (string) $dr->user_id,
                        'stage' => $stage,
                    ];
                    $stage++;
                }
            } elseif ($template->reviewers->isNotEmpty()) {
                $candidates = $template->reviewers
                    ->sortBy('stage')
                    ->values()
                    ->map(fn ($r): array => [
                        'reviewer_id' => (string) $r->user_id,
                        'stage' => (int) $r->stage,
                    ])
                    ->all();
            }
        }

        return $candidates;
    }

    /**
     * Publica el documento.
     */
    public function publishDocument(string $documentId, string $actorId, string $changelog): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->status !== 'in_review') {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede publicar un documento en revisión.'],
            ]);
        }

        if ($this->documentRepository->countPendingReviewsForDocument($documentId) > 0) {
            throw ValidationException::withMessages([
                'reviews' => ['Quedan revisiones pendientes.'],
            ]);
        }

        return $this->documentRepository->transaction(function () use ($documentId, $actorId, $changelog) {
            $this->documentStateService->transition($documentId, 'published', $actorId, [
                'published_at' => now(),
            ]);
            $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                documentId: $documentId,
                triggerEvent: 'published',
                triggeredBy: $actorId,
                notes: $changelog,
            ));
            $latestVersion = $this->documentRepository->findLatestDocumentVersionOrFail($documentId);

            $this->entityVersionLifecycleService->createPublishedSnapshotVersion(
                Document::class,
                $documentId,
                (int) $latestVersion->version_number,
                is_array($latestVersion->snapshot_data) ? $latestVersion->snapshot_data : [],
                $actorId,
                $changelog,
            );

            return $this->documentRepository->findOrFail($documentId);
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

        return $this->documentRepository->updateOwner($document, $newOwnerId);
    }

    /**
     * Lista las revisiones del documento.
     * 
     * @return Collection<int, \App\Models\DocumentReview>
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
    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersion
    {
        return $this->documentVersionService->findDocumentVersionOrFail($documentId, $versionId);
    }

    /**
     * Detalle de versión del documento aceptando id legacy o id polimórfico.
     *
     * @return array{
     *   id: string,
     *   document_id: string,
     *   version_number: int,
     *   trigger_event: string,
     *   triggered_by: string,
     *   changelog: ?string,
     *   snapshot_data: array<string, mixed>,
     *   created_at: ?string
     * }
     */
    public function findDocumentVersionDetailOrFail(string $documentId, string $versionId): array
    {
        return $this->documentVersionService->findDocumentVersionDetailOrFail($documentId, $versionId);
    }

    /**
     * Metadatos de versiones del documento (sin snapshot completo).
     *
     * @return list<array{
     *   id: string,
     *   document_id: string,
     *   version_number: int,
     *   trigger_event: string,
     *   triggered_by: string,
     *   changelog: ?string,
     *   notes: ?string,
     *   created_at: ?string
     * }>
     */
    public function listDocumentVersions(string $documentId): array
    {
        return $this->documentVersionService->listDocumentVersions($documentId);
    }
}
