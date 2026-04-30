<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Models\CourseModule;
use App\Models\Document;
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
        private readonly DocumentBlockService $documentBlockService,
        private readonly DocumentVersionService $documentVersionService,
        private readonly DocumentShareService $documentShareService,
        private readonly DocumentStateService $documentStateService,
        private readonly DocumentReviewService $documentReviewService,
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
     * @return list<array{template_id: string, template_version_id: string, process_id: string, name: string, description: ?string}>
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
            processId: $processId,
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

        return DB::transaction(function () use ($documentId, $actorId, $document) {
            $this->documentRepository->deleteReviewsForDocument($documentId);

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

            $document = $this->documentStateService->transition($documentId, 'in_review', $actorId, [
                'submitted_at' => now(),
            ]);

            if ($candidates !== []) {
                $this->documentRepository->createPendingReviews($documentId, $candidates);
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
            $this->documentStateService->transition($documentId, 'published', $actorId, [
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
     * Delega la propiedad del documento a otro usuario.
     */
    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);

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
