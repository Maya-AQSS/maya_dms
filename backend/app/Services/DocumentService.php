<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentVersion;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Enums\TemplateVisibilityLevel;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\TemplateContextResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        private readonly TemplateContextResolver $contextResolver,
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

        $snapshot = $this->documentBlockService->templatePublicationDefinitionRowsFromEntityVersion($ev);
        if ($snapshot === []) {
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

        $templateMeta = is_array($ev->snapshot_data ?? null) ? ($ev->snapshot_data['template'] ?? null) : null;
        $ctx = $this->contextResolver->resolve($dto, is_array($templateMeta) ? $templateMeta : null);

        return $this->documentRepository->createDocumentWithBlocks([
            'process_id'          => $dto->processId,
            'template_id'         => $dto->templateId,
            'template_version_id' => (string) $ev->id,
            'title'               => $dto->title,
            'study_type_id'       => $ctx['studyTypeId'],
            'study_id'            => $ctx['studyId'],
            'module_id'           => $ctx['moduleId'],
            'team_id'             => $ctx['teamId'],
            'delivery_deadline'   => $dto->deliveryDeadline,
            'created_by'          => $dto->createdBy,
            'owner_id'            => $dto->ownerId,
            'status'              => 'draft',
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
                'delivery_deadline' => $source->delivery_deadline,
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
            'delivery_deadline' => $deliveryDeadline,
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
     * @return list<array{
     *   template_id: string,
     *   template_version_id: string,
     *   process_id: string,
     *   name: string,
     *   description: ?string,
     *   visibility_level: string,
     *   team_id: ?string,
     *   team_name: ?string
     * }>
     */
    public function creationOptionsForModule(string $moduleId): array
    {
        $templates = $this->templateRepository->listPublishedByModule($moduleId);
        if ($templates->isEmpty()) {
            return [];
        }

        $templateIds = $templates->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        // Batch: one query for the latest published version of each template.
        $maxVersions = DB::table('entity_versions')
            ->where('versionable_type', Template::class)
            ->whereIn('versionable_id', $templateIds)
            ->where('status', 'published')
            ->where('version_number', '>', 0)
            ->groupBy('versionable_id')
            ->select('versionable_id', DB::raw('MAX(version_number) as max_version'));

        $publishedById = DB::table('entity_versions as ev')
            ->joinSub($maxVersions, 'mv', function ($join) {
                $join->on('ev.versionable_id', '=', 'mv.versionable_id')
                     ->on('ev.version_number', '=', 'mv.max_version');
            })
            ->where('ev.versionable_type', Template::class)
            ->where('ev.status', 'published')
            ->get(['ev.id', 'ev.versionable_id'])
            ->keyBy('versionable_id');

        // Batch: one query for all distinct team names.
        $teamIds = $templates->pluck('team_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
        $teamNames = $teamIds !== []
            ? DB::table('teams')->whereIn('id', $teamIds)->pluck('name', 'id')
            : collect();

        $options = [];
        foreach ($templates as $template) {
            $templateId = (string) $template->id;
            $published = $publishedById->get($templateId);
            if ($published === null) {
                continue;
            }

            $options[] = [
                'template_id' => $template->id,
                'template_version_id' => (string) $published->id,
                'process_id' => (string) $template->process_id,
                'name' => (string) $template->name,
                'description' => $template->description,
                'visibility_level' => $template->visibility_level instanceof TemplateVisibilityLevel
                    ? $template->visibility_level->value
                    : (string) $template->visibility_level,
                'team_id' => $template->team_id !== null ? (string) $template->team_id : null,
                'team_name' => $template->team_id !== null
                    ? ($teamNames->get((string) $template->team_id) ?: null)
                    : null,
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

        $ev = EntityVersion::query()
            ->whereKey($versionId)
            ->where('versionable_type', Template::class)
            ->where('status', 'published')
            ->first();

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
        $this->assertDocumentMetadataInvariantsForMutation(
            (string) $document->title,
            $document->delivery_deadline,
        );

        return $this->documentStateService->transition($documentId, 'draft', $actorId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeUpdateAttributesAgainstDocumentAndTemplate(Document $document, array $attributes): array
    {
        $normalized = $attributes;
        $templateMeta = $this->resolveTemplateMetaForDocument($document);
        if ($templateMeta === null) {
            return $normalized;
        }

        $visibility = $templateMeta['visibility_level'] ?? null;
        if (! is_string($visibility) || $visibility === '') {
            return $normalized;
        }

        $studyTypeId = array_key_exists('study_type_id', $attributes) ? $attributes['study_type_id'] : $document->study_type_id;
        $studyId = array_key_exists('study_id', $attributes) ? $attributes['study_id'] : $document->study_id;
        $moduleId = array_key_exists('module_id', $attributes) ? $attributes['module_id'] : $document->module_id;

        $templateStudyTypeId = isset($templateMeta['study_type_id']) && is_string($templateMeta['study_type_id']) && $templateMeta['study_type_id'] !== ''
            ? $templateMeta['study_type_id']
            : null;
        $templateStudyId = isset($templateMeta['study_id']) && is_string($templateMeta['study_id']) && $templateMeta['study_id'] !== ''
            ? $templateMeta['study_id']
            : null;
        $templateModuleId = isset($templateMeta['module_id']) && is_string($templateMeta['module_id']) && $templateMeta['module_id'] !== ''
            ? $templateMeta['module_id']
            : null;

        if ($visibility === TemplateVisibilityLevel::Personal->value || $visibility === TemplateVisibilityLevel::Team->value) {
            $normalized['study_type_id'] = $templateStudyTypeId;
            $normalized['study_id'] = $templateStudyId;
            $normalized['module_id'] = $templateModuleId;
            return $normalized;
        }

        if ($visibility === TemplateVisibilityLevel::Module->value) {
            if ($templateModuleId !== null) {
                if ($moduleId !== null && $moduleId !== $templateModuleId) {
                    throw ValidationException::withMessages([
                        'module_id' => ['El documento debe mantenerse en el mismo módulo de la plantilla.'],
                    ]);
                }
                $normalized['module_id'] = $templateModuleId;
            }
            $normalized['study_id'] = $templateStudyId;
            $normalized['study_type_id'] = $templateStudyTypeId;
            return $normalized;
        }

        if ($visibility === TemplateVisibilityLevel::Study->value) {
            if ($templateStudyId !== null) {
                if ($studyId !== null && $studyId !== $templateStudyId) {
                    throw ValidationException::withMessages([
                        'study_id' => ['El documento debe mantenerse en el mismo estudio de la plantilla.'],
                    ]);
                }
                $normalized['study_id'] = $templateStudyId;
            }
            if (is_string($moduleId) && $moduleId !== '') {
                $moduleStudyId = DB::table('course_modules')
                    ->where('id', $moduleId)
                    ->value('study_id');
                if (! is_string($moduleStudyId) || $moduleStudyId !== $templateStudyId) {
                    throw ValidationException::withMessages([
                        'module_id' => ['El módulo debe pertenecer al mismo estudio de la plantilla.'],
                    ]);
                }
                $normalized['module_id'] = $moduleId;
            }
            $normalized['study_type_id'] = $templateStudyTypeId;
            return $normalized;
        }

        if ($visibility === TemplateVisibilityLevel::StudyType->value) {
            if ($templateStudyTypeId !== null) {
                $normalized['study_type_id'] = $templateStudyTypeId;
            }

            if (is_string($moduleId) && $moduleId !== '') {
                $module = DB::table('course_modules')
                    ->join('studies', 'studies.id', '=', 'course_modules.study_id')
                    ->where('course_modules.id', $moduleId)
                    ->select('course_modules.study_id', 'studies.study_type_id')
                    ->first();
                if (! $module || (string) $module->study_type_id !== $templateStudyTypeId) {
                    throw ValidationException::withMessages([
                        'module_id' => ['El módulo debe pertenecer a un estudio del mismo tipo que la plantilla.'],
                    ]);
                }
                if (is_string($studyId) && $studyId !== '' && $studyId !== (string) $module->study_id) {
                    throw ValidationException::withMessages([
                        'study_id' => ['El estudio indicado no corresponde con el módulo seleccionado.'],
                    ]);
                }
                $normalized['module_id'] = $moduleId;
                $normalized['study_id'] = (string) $module->study_id;

                return $normalized;
            }

            if (is_string($studyId) && $studyId !== '') {
                $studyTypeFromStudy = DB::table('studies')
                    ->where('id', $studyId)
                    ->value('study_type_id');
                if (! is_string($studyTypeFromStudy) || $studyTypeFromStudy !== $templateStudyTypeId) {
                    throw ValidationException::withMessages([
                        'study_id' => ['El estudio debe pertenecer al mismo tipo de estudio de la plantilla.'],
                    ]);
                }
            }

            return $normalized;
        }

        if ($visibility === TemplateVisibilityLevel::Global->value) {
            if (is_string($moduleId) && $moduleId !== '') {
                $module = DB::table('course_modules')
                    ->where('id', $moduleId)
                    ->select('study_id')
                    ->first();
                if (! $module || ! is_string($module->study_id)) {
                    throw ValidationException::withMessages([
                        'module_id' => ['El módulo seleccionado no existe.'],
                    ]);
                }
                if (is_string($studyId) && $studyId !== '' && $studyId !== (string) $module->study_id) {
                    throw ValidationException::withMessages([
                        'study_id' => ['El estudio indicado no corresponde con el módulo seleccionado.'],
                    ]);
                }
            }
        }

        return $normalized;
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

            if ($head === null || (string) $head->id !== $versionId || (int) $head->version_number !== 0) {
                throw ValidationException::withMessages([
                    'version' => ['Solo se puede descartar la versión de trabajo actual del documento.'],
                ]);
            }

            if (! in_array((string) $head->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'version' => ['Solo se pueden descartar versiones no publicadas (draft/in_review).'],
                ]);
            }

            $latestPublished = $this->entityVersionRepository->findLatestPublishedForEntity(Document::class, $documentId);
            if ($latestPublished === null || ! is_array($latestPublished->snapshot_data)) {
                throw ValidationException::withMessages([
                    'version' => ['No existe una versión publicada a la que restaurar.'],
                ]);
            }

            $publishedSnapshot = $latestPublished->snapshot_data;
            $publishedBlocks = isset($publishedSnapshot['blocks']) && is_array($publishedSnapshot['blocks'])
                ? $publishedSnapshot['blocks']
                : [];

            $head->snapshot_data = $publishedSnapshot;
            $head->status = 'published';
            $head->updated_at = now();
            $head->save();

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
     * @param list<array<string, mixed>> $publishedBlocks
     */
    private function restorePublishedDocumentBlocks(string $documentId, array $publishedBlocks): void
    {
        $existingByTemplateBlock = DocumentBlock::query()
            ->where('document_id', $documentId)
            ->get()
            ->filter(fn (DocumentBlock $block): bool => is_string($block->template_block_id) && $block->template_block_id !== '')
            ->keyBy(fn (DocumentBlock $block): string => (string) $block->template_block_id);

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
                $existing->fill($payload);
                $existing->save();
                continue;
            }

            DocumentBlock::query()->create([
                'id' => (string) Str::uuid(),
                'document_id' => $documentId,
                'template_block_id' => $templateBlockId,
                ...$payload,
            ]);
        }

        if ($seenTemplateBlockIds === []) {
            DocumentBlock::query()->where('document_id', $documentId)->delete();
            return;
        }

        DocumentBlock::query()
            ->where('document_id', $documentId)
            ->whereNotIn('template_block_id', $seenTemplateBlockIds)
            ->delete();
    }

    /**
     * Envia el documento a revisión.
     */
    public function submitToReview(string $documentId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if (! in_array($document->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Solo los documentos en borrador o rechazados pueden enviarse a revisión.'],
            ]);
        }

        $this->documentBlockService->assertMandatoryBlocksAreFilled($document);

        return $this->documentRepository->transaction(function () use ($documentId, $actorId, $document) {
            $this->documentRepository->deleteReviewsForDocument($documentId);

            $candidates = $this->resolveReviewCandidatesFromTemplateVersion($document);

            if ($candidates === []) {
                // No resolvable reviewer pool for this document → auto-publish regardless of
                // visibility level. The template may simply have no validators configured, which
                // is a valid state (e.g. personal use, or a coordinator who skipped step 3).
                $this->documentStateService->transition($documentId, 'published', $actorId);

                // Misma convención que {@see TemplatePublishingService} (plantilla ya numerada en creación).
                $autoChangelog = 'Publicación automática (sin revisores configurados)';
                $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                    documentId: $documentId,
                    triggerEvent: 'published',
                    triggeredBy: $actorId,
                    notes: $autoChangelog,
                ));

                return $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
            }

            $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                documentId: $documentId,
                triggerEvent: 'submitted',
                triggeredBy: $actorId,
                notes: 'Envío a revisión',
            ));

            $document = $this->documentStateService->transition($documentId, 'in_review', $actorId);
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

            $this->documentBlockService->assertMandatoryBlocksAreFilled($document);
        }

        if ($document->status === 'in_review' && $this->documentRepository->countPendingReviewsForDocument($documentId) > 0) {
            throw ValidationException::withMessages([
                'reviews' => ['Quedan revisiones pendientes.'],
            ]);
        }

        return $this->documentRepository->transaction(function () use ($documentId, $actorId, $changelog) {
            $trimmedChangelog = is_string($changelog) ? trim($changelog) : '';
            $resolvedChangelog = $trimmedChangelog !== '' ? $trimmedChangelog : 'Publicación automática';

            $this->documentStateService->transition($documentId, 'published', $actorId);
            $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                documentId: $documentId,
                triggerEvent: 'published',
                triggeredBy: $actorId,
                notes: $resolvedChangelog,
            ));

            return $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
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

    public function attachLatestPublishedVersionMeta(Collection $documents): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $ids = $documents->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->values()->all();
        if ($ids === []) {
            return;
        }

        $maxVersions = DB::table('entity_versions')
            ->where('versionable_type', Document::class)
            ->whereIn('versionable_id', $ids)
            ->where('status', 'published')
            ->where('version_number', '>', 0)
            ->groupBy('versionable_id')
            ->select('versionable_id', DB::raw('MAX(version_number) as max_version'));

        $rows = DB::table('entity_versions as ev')
            ->joinSub($maxVersions, 'mv', function ($join) {
                $join->on('ev.versionable_id', '=', 'mv.versionable_id')
                     ->on('ev.version_number', '=', 'mv.max_version');
            })
            ->where('ev.versionable_type', Document::class)
            ->where('ev.status', 'published')
            ->get(['ev.versionable_id', 'ev.id', 'ev.version_number', 'ev.snapshot_data']);

        $latestByDocument = [];
        foreach ($rows as $row) {
            $documentId = (string) $row->versionable_id;
            $latestByDocument[$documentId] = (object) [
                'id' => (string) $row->id,
                'version_number' => (int) $row->version_number,
                'title' => $this->extractPublishedTitleFromSnapshot($row->snapshot_data),
            ];
        }

        foreach ($documents as $document) {
            $meta = $latestByDocument[(string) $document->id] ?? null;
            $document->setAttribute('latest_published_version_id', $meta?->id);
            $document->setAttribute('latest_published_version_number', $meta?->version_number);
            $document->setAttribute('latest_published_title', $meta?->title);
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

        $versionNumberById = DB::table('entity_versions')
            ->whereIn('id', $versionIds)
            ->pluck('version_number', 'id')
            ->map(fn ($value) => (int) $value)
            ->all();

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
}
