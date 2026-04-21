<?php

namespace App\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Events\DocumentStateChanged;
use App\Models\CourseModule;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
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
            templateVersionId: $selected['template_version_id'],
        ));
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

            $out[] = [
                'document_block_id' => $row?->id,
                'template_block_id' => $tid,
                'type' => $def['type'] ?? '',
                'title' => $def['title'] ?? null,
                'default_content' => $def['default_content'] ?? null,
                'block_state' => $def['block_state'] ?? 'editable',
                'mandatory' => (bool) ($def['mandatory'] ?? false),
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
        $document = $this->documentRepository->findOrFail($dto->documentId);
        if ($document->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden editar bloques de documentos en borrador.'],
            ]);
        }

        $block = $this->documentRepository->findBlockInDocumentOrFail(
            $dto->documentId,
            $dto->documentBlockId,
        );

        $definitions = collect($this->blockDefinitionsForDocument($document))
            ->keyBy(fn (array $def) => (string) $def['id']);
        $definition = $definitions->get((string) $block->template_block_id);

        if (($definition['block_state'] ?? 'editable') === 'locked') {
            throw ValidationException::withMessages([
                'block' => ['Este bloque está bloqueado y no admite edición.'],
            ]);
        }

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
            // usuario) pero debe poder generar revisiones a partir de `template_reviewers` de la plantilla anclada.
            $template = Template::query()
                ->withoutGlobalScopes(['user_access'])
                ->with(['reviewers' => fn ($q) => $q->orderBy('stage')])
                ->find($document->template_id);

            $rows = $template?->reviewers
                ?->sortBy('stage')
                ->map(fn ($r) => [
                    'reviewer_id' => $r->user_id,
                    'stage' => $r->stage,
                ])
                ->values()
                ->all() ?? [];

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
    public function publishDocument(string $documentId, string $actorId): Document
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

        return $this->transition($documentId, 'published', $actorId, [
            'published_at' => now(),
        ]);
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
            $this->documentRepository->deleteReviewsForDocument($documentId);

            return $this->transition($documentId, 'draft', $actorId, [
                'submitted_at' => null,
                'published_at' => null,
            ]);
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
    public function approveReview(string $documentId, string $reviewId, string $actorId): Document
    {
        return DB::transaction(function () use ($documentId, $reviewId, $actorId) {
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
                return $this->transition($documentId, 'published', $actorId, [
                    'published_at' => now(),
                ]);
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

            $this->documentRepository->deleteReviewsForDocument($documentId);

            return $this->transition($documentId, 'draft', $actorId, [
                'submitted_at' => null,
                'published_at' => null,
            ]);
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

    private function assertMandatoryBlocksAreFilled(Document $document): void
    {
        $definitions = collect($this->blockDefinitionsForDocument($document))
            ->filter(fn (array $def) => (bool) ($def['mandatory'] ?? false));

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
                'blocks' => ['Debes completar todos los bloques obligatorios antes de enviar a revisión.'],
                'missing_template_block_ids' => $missing,
            ]);
        }
    }
}
