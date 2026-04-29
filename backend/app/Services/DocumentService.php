<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Events\DocumentStateChanged;
use App\Models\CourseModule;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Models\DocumentVersion;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
        private readonly SnapshotServiceInterface $snapshotService,
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
            $version = $this->templateVersionRepository->findLatestPublishedForTemplate($dto->templateId);
            if ($version === null) {
                throw ValidationException::withMessages([
                    'template_id' => ['La plantilla no tiene versiones publicadas; no se puede crear un documento.'],
                ]);
            }
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
     * Actualiza metadatos editables del documento.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $documentId, array $attributes): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        $document->update([
            'title' => $attributes['title'],
            'delivery_deadline' => $attributes['delivery_deadline'] ?? null,
            'study_type_id' => $attributes['study_type_id'] ?? $document->study_type_id,
            'study_id' => $attributes['study_id'] ?? $document->study_id,
            'module_id' => $attributes['module_id'] ?? $document->module_id,
        ]);

        return $document->fresh();
    }

    /**
     * Borrado lógico del documento.
     */
    public function delete(string $documentId): void
    {
        $document = $this->documentRepository->findOrFail($documentId);
        $document->delete();
    }

    /**
     * Opciones de creación de documento disponibles para un módulo.
     * 
     * @return list<array{template_id: string, template_version_id: string, name: string, description: ?string}>
     */
    public function creationOptionsForModule(string $moduleId): array
    {
        $templates = $this->templateRepository->listPublishedByModule($moduleId);

        $options = [];
        foreach ($templates as $template) {
            $version = $this->templateVersionRepository->findLatestPublishedForTemplate($template->id);
            if ($version === null) {
                continue;
            }

            $options[] = [
                'template_id' => $template->id,
                'template_version_id' => $version->id,
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

        $module = CourseModule::query()->with('study')->find($moduleId);
        if ($module === null) {
            throw ValidationException::withMessages([
                'module_id' => ['El módulo no existe.'],
            ]);
        }

        $studyTypeId = $module->study !== null ? (string) $module->study->study_type_id : null;

        return $this->create(new CreateDocumentDto(
            templateId: $selected['template_id'],
            title: 'Nueva Programación Didáctica',
            createdBy: $creatorId,
            ownerId: $creatorId,
            studyTypeId: $studyTypeId,
            studyId: (string) $module->study_id,
            moduleId: $moduleId,
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
        $versionId = $document->template_version_id;

        $currentFull = is_string($versionId)
            ? $this->templateVersionRepository->findPublishedMetaById($versionId)
            : null;

        $latestFull = $this->templateVersionRepository->findLatestPublishedMetaForTemplate((string) $document->template_id);

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
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->owner_id !== $actorId) {
            abort(403, 'Solo el titular puede gestionar colaboradores.');
        }

        if ($targetUserId === $actorId) {
            throw ValidationException::withMessages([
                'user_id' => ['No puedes compartir el documento contigo mismo.'],
            ]);
        }

        if ($targetUserId === $document->owner_id) {
            throw ValidationException::withMessages([
                'user_id' => ['El titular ya tiene acceso completo al documento.'],
            ]);
        }

        $this->documentRepository->upsertDocumentShare(
            $documentId,
            $targetUserId,
            $permission,
            $actorId,
        );

        return [
            'user_id' => $targetUserId,
            'permission' => $permission,
            'granted_by' => $actorId,
        ];
    }

    /**
     * Elimina un compartido (idempotente si no existía).
     */
    public function removeDocumentShare(string $documentId, string $targetUserId, string $actorId): void
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->owner_id !== $actorId) {
            abort(403, 'Solo el titular puede gestionar colaboradores.');
        }

        $this->documentRepository->deleteDocumentShare($documentId, $targetUserId);
    }

    /**
     * Anota en cada documento si el visor accede vía `document_shares` y con qué permiso (listado / detalle).
     * 
     * @param  Collection<int, Document>  $documents
     */
    public function attachShareMetadataForViewer(Collection $documents, string $viewerId): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $ids = $documents->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $byDoc = $this->documentRepository->sharePermissionsForViewer($ids, $viewerId);

        foreach ($documents as $document) {
            $permission = $byDoc[(string) $document->getKey()] ?? null;
            $document->setAttribute('viewer_share_permission', $permission);
            $document->setAttribute('is_shared_with_me', $permission !== null);
        }
    }

    /**
     * Lista documentos visibles para el usuario actual ordenados por fecha de creación descendente.
     * 
     * @return Collection<int, Document>
     */
    public function listOrderedByCreatedAtDesc(): Collection
    {
        return $this->documentRepository->listOrderedByCreatedAtDesc();
    }

    /**
     * Bloques para mostrar/editar: definición según {@see Document::$template_version_id} y contenido en document_blocks.
     *
     * @return list<array<string, mixed>>
     */
    public function blocksForDisplay(Document $document): array
    {
        $document->loadMissing(['blocks', 'templateVersion']);

        $byTemplateBlockId = $document->blocks->keyBy('template_block_id');
        $definitions = $this->blockDefinitionsForDocument($document);

        $out = [];
        foreach ($definitions as $def) {
            $tid = (string) $def['id'];
            $row = $byTemplateBlockId->get($tid);
            $mandatory = (bool) ($def['mandatory'] ?? false);
            $state = (string) ($def['block_state'] ?? 'editable');

            $out[] = [
                'document_block_id' => $row?->id,
                'template_block_id' => $tid,
                'type' => $def['type'] ?? '',
                'title' => $def['title'] ?? null,
                'description' => $def['description'] ?? null,
                'default_content' => $def['default_content'] ?? null,
                'block_state' => $state,
                'mandatory' => $mandatory,
                'sort_order' => (int) ($def['sort_order'] ?? 0),
                'content' => $row?->content,
                'is_filled' => (bool) ($row?->is_filled ?? false),
            ];
        }

        return $out;
    }

    /**
     * Actualiza el contenido de un bloque de documento.
     *
     * @return array<string, mixed>
     */
    public function updateBlock(UpdateDocumentBlockDto $dto): array
    {
        return DB::transaction(function () use ($dto) {
            $document = $this->documentRepository->findOrFail($dto->documentId);
            if ($document->status !== 'draft') {
                abort(403, 'Solo se pueden editar bloques de documentos en borrador.');
            }

            $block = $this->documentRepository->findBlockInDocumentOrFail(
                $dto->documentId,
                $dto->documentBlockId,
            );

            $definitions = collect($this->blockDefinitionsForDocument($document))
                ->keyBy(fn (array $def) => (string) $def['id']);
            $definition = $definitions->get((string) $block->template_block_id) ?? [];

            $state = (string) ($definition['block_state'] ?? 'editable');

            if ($state === 'locked') {
                abort(403, 'Este bloque está bloqueado y no admite edición.');
            }

            if ($this->documentBlockContentEquals($block->content, $dto->content)) {
                return [
                    'document_block_id' => (string) $block->id,
                    'template_block_id' => (string) $block->template_block_id,
                    'content' => $block->content,
                    'is_filled' => (bool) $block->is_filled,
                    'last_edited_by' => (string) $block->last_edited_by,
                    'updated_at' => $block->updated_at?->toIso8601String(),
                ];
            }

            $this->appendModifiableBlockVersionSnapshotsIfNeeded($document, $block, $definition, $dto);

            $block->content = $dto->content;
            $block->is_filled = $this->isContentFilled($dto->content);
            $block->last_edited_by = $dto->actorId;
            $this->documentRepository->saveBlock($block);

            return [
                'document_block_id' => (string) $block->id,
                'template_block_id' => (string) $block->template_block_id,
                'content' => $block->content,
                'is_filled' => (bool) $block->is_filled,
                'last_edited_by' => (string) $block->last_edited_by,
                'updated_at' => $block->updated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Bloques para mostrar/editar: definición según {@see Document::$template_version_id} y contenido en document_blocks.
     *
     * @return list<array<string, mixed>>
     */
    private function blockDefinitionsForDocument(Document $document): array
    {
        if ($document->template_version_id !== null) {
            $document->loadMissing('templateVersion');
            if ($document->templateVersion !== null) {
                $snap = $document->templateVersion->blocks_snapshot;

                return collect(is_array($snap) ? $snap : [])
                    ->sortBy(fn ($b) => $b['sort_order'] ?? 0)
                    ->values()
                    ->all();
            }
        }

        // Sin global scope: durante submit/revisión el titular puede no tener visibilidad
        // de catálogo sobre la plantilla, pero el documento ya está anclado a ella.
        $template = Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->with(['blocks' => fn ($q) => $q->orderBy('sort_order')])
            ->findOrFail($document->template_id);

        return $template->blocks->map(fn ($b) => [
            'id' => $b->id,
            'type' => $b->type,
            'title' => $b->title,
            'description' => $b->description,
            'default_content' => $b->default_content,
            'block_state' => $b->block_state,
            'mandatory' => $b->mandatory,
            'sort_order' => $b->sort_order,
        ])->all();
    }

    /**
     * Transiciona el documento a un nuevo estado y emite el evento de dominio DocumentStateChanged.
     * 
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = []): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);
        $oldStatus = $document->status;

        $document->update(array_merge(['status' => $newStatus], $extraAttributes));

        event(new DocumentStateChanged(
            document: $document->fresh(),
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId: $actorId,
        ));

        return $document->fresh();
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

        $this->assertMandatoryBlocksAreFilled($document);

        return DB::transaction(function () use ($documentId, $actorId, $document) {
            $this->documentRepository->deleteReviewsForDocument($documentId);

            // Sin global scope: el titular puede no “ver” la plantilla en catálogo (p. ej. personal de otro
            // usuario) pero debe poder generar revisiones desde la plantilla anclada.
            //
            // Prioridad: `template_document_reviewers` (validadores de documento en el asistente de plantilla);
            // si no hay ninguno, se usa `template_reviewers` (revisores normativos de la plantilla).
            // En ambos casos se excluye al creador y titular del documento (SoD, {@see DocumentPolicy::review}).
            $template = Template::query()
                ->withoutGlobalScopes(['user_access'])
                ->with([
                    'reviewers' => fn ($q) => $q->orderBy('stage'),
                    'documentReviewers' => fn ($q) => $q->orderBy('created_at')->orderBy('user_id'),
                ])
                ->find($document->template_id);

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

            $ownerId = (string) $document->owner_id;
            $createdById = (string) $document->created_by;
            $rows = array_values(array_filter(
                $candidates,
                static fn (array $row): bool => $row['reviewer_id'] !== $ownerId && $row['reviewer_id'] !== $createdById,
            ));

            if ($candidates !== [] && $rows === []) {
                throw ValidationException::withMessages([
                    'reviewers' => [
                        'Los validadores configurados coinciden todos con el titular o creador del documento. Añade en la plantilla al menos un validador distinto del titular y del creador.',
                    ],
                ]);
            }

            $document = $this->transition($documentId, 'in_review', $actorId, [
                'submitted_at' => now(),
            ]);

            if ($rows !== []) {
                $this->documentRepository->createPendingReviews($documentId, $rows);
            }

            return $document;
        });
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

        return DB::transaction(function () use ($documentId, $actorId, $changelog) {
            $this->transition($documentId, 'published', $actorId, [
                'published_at' => now(),
            ]);
            $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                documentId: $documentId,
                triggerEvent: 'published',
                triggeredBy: $actorId,
                notes: $changelog,
            ));

            return $this->documentRepository->findOrFail($documentId);
        });
    }

    /**
     * Rechaza el documento.
     */
    public function rejectDocument(string $documentId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if (! in_array($document->status, ['in_review', 'published'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede rechazar un documento en revisión o publicado.'],
            ]);
        }

        return DB::transaction(function () use ($documentId, $actorId) {
            $updated = $this->transition($documentId, 'draft', $actorId, [
                'submitted_at' => null,
                'published_at' => null,
            ]);
            $this->documentRepository->deleteReviewsForDocument($documentId);

            return $updated;
        });
    }

    /**
     * Delega la propiedad del documento a otro usuario.
     */
    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->owner_id !== $actorId) {
            throw ValidationException::withMessages([
                'owner' => ['Solo el titular actual puede delegar el documento.'],
            ]);
        }

        if ($newOwnerId === $document->owner_id) {
            throw ValidationException::withMessages([
                'new_owner_id' => ['El nuevo titular debe ser distinto del actual.'],
            ]);
        }

        $document->update(['owner_id' => $newOwnerId]);

        return $document->fresh();
    }

    /**
     * Lista las revisiones del documento.
     * 
     * @return Collection<int, DocumentReview>
     */
    public function listReviews(string $documentId): Collection
    {
        $this->documentRepository->findOrFail($documentId);

        return $this->documentRepository->listReviewsForDocument($documentId);
    }

    /**
     * Aprueba una revisión del documento.
     */
    public function approveReview(string $documentId, string $reviewId, string $actorId, ?string $publicationChangelog = null): Document
    {
        return DB::transaction(function () use ($documentId, $reviewId, $actorId, $publicationChangelog) {
            $document = $this->documentRepository->findOrFail($documentId);

            if ($document->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Las revisiones solo aplican a documentos en revisión.'],
                ]);
            }

            $review = $this->documentRepository->findReviewInDocument($reviewId, $documentId);

            if ($review === null) {
                abort(404);
            }

            if ($review->reviewer_id !== $actorId) {
                abort(403, 'No eres el revisor asignado a esta etapa.');
            }

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => ['Esta revisión ya fue procesada.'],
                ]);
            }

            $this->assertSequentialReviewAllowsActing($document, $review);

            $review->status = 'approved';
            $review->reviewed_at = now();
            $this->documentRepository->saveReview($review);

            if ($this->documentRepository->countPendingReviewsForDocument($documentId) === 0) {
                $this->transition($documentId, 'published', $actorId, [
                    'published_at' => now(),
                ]);
                $changelog = $publicationChangelog !== null && trim($publicationChangelog) !== ''
                    ? trim($publicationChangelog)
                    : 'Publicado tras aprobación de revisión.';
                $this->snapshotService->createDocumentSnapshot(new CreateDocumentSnapshotDto(
                    documentId: $documentId,
                    triggerEvent: 'published',
                    triggeredBy: $actorId,
                    notes: $changelog,
                ));

                return $this->documentRepository->findOrFail($documentId);
            }

            return $this->documentRepository->findOrFail($documentId);
        });
    }

    /**
     * Rechaza una revisión del documento.
     */
    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): Document
    {
        return DB::transaction(function () use ($documentId, $reviewId, $actorId, $reason) {
            $document = $this->documentRepository->findOrFail($documentId);

            if ($document->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Las revisiones solo aplican a documentos en revisión.'],
                ]);
            }

            $review = $this->documentRepository->findReviewInDocument($reviewId, $documentId);

            if ($review === null) {
                abort(404);
            }

            if ($review->reviewer_id !== $actorId) {
                abort(403, 'No eres el revisor asignado a esta etapa.');
            }

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => ['Esta revisión ya fue procesada.'],
                ]);
            }

            $this->assertSequentialReviewAllowsActing($document, $review);

            $review->status = 'rejected';
            $review->rejection_reason = $reason;
            $review->reviewed_at = now();
            $this->documentRepository->saveReview($review);


            $updated = $this->transition($documentId, 'draft', $actorId, [
                'submitted_at' => null,
                'published_at' => null,
            ]);

            $this->documentRepository->deleteReviewsForDocument($documentId);

            return $updated;
        });
    }

    /**
     * En modo {@see Template::$review_mode} secuencial, solo puede actuar quien pertenezca
     * a la etapa pendiente con número más bajo (así se respetan varios revisores en la misma etapa).
     * En modo paralelo no hay orden entre etapas.
     */
    private function assertSequentialReviewAllowsActing(Document $document, DocumentReview $review): void
    {
        $document->loadMissing('template');
        $mode = $document->template?->review_mode ?? 'parallel';
        if ($mode !== 'sequential') {
            return;
        }

        $minStage = $this->documentRepository->minPendingReviewStageForDocument($document->id);
        if ($minStage === null) {
            return;
        }

        if ($review->stage !== $minStage) {
            throw ValidationException::withMessages([
                'review' => ['En revisión secuencial, solo puede actuar la etapa pendiente más baja.'],
            ]);
        }
    }

    /**
     * Verifica si el contenido del bloque está completado.
     */
    private function isContentFilled(mixed $content): bool
    {
        if ($content === null) {
            return false;
        }

        if (is_string($content)) {
            return trim($content) !== '';
        }

        if (is_array($content)) {
            if ($content === []) {
                return false;
            }

            $encoded = json_encode($content);

            return $encoded !== false && $encoded !== '[]' && $encoded !== '{}' && $encoded !== 'null';
        }

        return true;
    }

    /**
     * Verifica que todos los bloques no opcionales estén completos.
     */
    private function assertMandatoryBlocksAreFilled(Document $document): void
    {
        $definitions = collect($this->blockDefinitionsForDocument($document))
            ->filter(fn (array $def) => ($def['block_state'] ?? 'editable') !== 'optional');

        if ($definitions->isEmpty()) {
            return;
        }

        $document->loadMissing('blocks');
        $blocksByTemplateBlockId = $document->blocks->keyBy('template_block_id');
        $missing = [];

        foreach ($definitions as $definition) {
            $templateBlockId = (string) ($definition['id'] ?? '');
            if ($templateBlockId === '') {
                continue;
            }

            $block = $blocksByTemplateBlockId->get($templateBlockId);
            if ($block === null) {
                $missing[] = $templateBlockId;
                continue;
            }

            if (! ((bool) $block->is_filled) && ! $this->isContentFilled($block->content)) {
                $missing[] = $templateBlockId;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'blocks' => ['Debes completar todos los bloques no opcionales antes de enviar a revisión.'],
                'missing_template_block_ids' => $missing,
            ]);
        }
    }

    /**
     * Localiza una versión de documento por su ID.
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersion
    {
        $this->documentRepository->findOrFail($documentId);

        return $this->documentRepository->findDocumentVersionInDocumentOrFail($documentId, $versionId);
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
        $document = $this->documentRepository->findOrFail($documentId);

        return $document->versions()
            ->orderByDesc('version_number')
            ->get()
            ->map(static function (DocumentVersion $v): array {
                return [
                    'id' => $v->id,
                    'document_id' => $v->document_id,
                    'version_number' => $v->version_number,
                    'trigger_event' => $v->trigger_event,
                    'triggered_by' => $v->triggered_by,
                    'changelog' => $v->notes,
                    'notes' => $v->notes,
                    'created_at' => $v->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Compara dos valores JSON canónicamente.
     */
    private function documentBlockContentEquals(mixed $a, mixed $b): bool
    {
        return $this->jsonEncodeCanonical($a) === $this->jsonEncodeCanonical($b);
    }

    /**
     * Codifica un valor JSON canónicamente.
     */
    private function jsonEncodeCanonical(mixed $value): string
    {
        try {
            $normalized = $this->normalizeKeysForCanonicalJson($value);

            return json_encode(
                $normalized,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (\JsonException) {
            return '';
        }
    }

    /**
     * Ordena claves de objetos JSON (arrays asociativos) de forma recursiva; preserva el orden de listas.
     */
    private function normalizeKeysForCanonicalJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === [] || array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeKeysForCanonicalJson($item), $value);
        }

        ksort($value);

        foreach ($value as $k => $nested) {
            $value[$k] = $this->normalizeKeysForCanonicalJson($nested);
        }

        return $value;
    }

    /**
     * Si el bloque es editable y modificable, crea una nueva versión del bloque.
     * 
     * @param  array<string, mixed>  $definition
     */
    private function appendModifiableBlockVersionSnapshotsIfNeeded(
        Document $document,
        DocumentBlock $block,
        array $definition,
        UpdateDocumentBlockDto $dto,
    ): void {
        if (($definition['block_state'] ?? 'editable') !== 'modifiable') {
            return;
        }

        $blockId = (string) $block->id;
        $documentId = (string) $document->id;
        $max = $this->documentRepository->maxBlockVersionNumberForDocumentBlock($blockId);

        if ($max === 0) {
            $baseline = $this->normalizeBlockVersionPayload($definition['default_content'] ?? null);
            $baselineEditor = (string) ($document->created_by ?? $document->owner_id ?? $dto->actorId);
            $this->documentRepository->insertDocumentBlockVersion(
                $blockId,
                $documentId,
                1,
                $baseline,
                null,
                $baselineEditor,
            );
            $max = 1;
        }

        $this->documentRepository->insertDocumentBlockVersion(
            $blockId,
            $documentId,
            $max + 1,
            $this->normalizeBlockVersionPayload($dto->content),
            null,
            $dto->actorId,
        );
    }

    /**
     * Normaliza el payload del bloque para la versión.
     * 
     * @return array<string, mixed>
     */
    private function normalizeBlockVersionPayload(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [];
    }
}
