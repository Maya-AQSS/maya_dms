<?php

namespace App\Services;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\SyncUsersDto;
use App\DTOs\Templates\TemplateDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\Enums\TemplateVisibilityLevel;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TemplateService implements TemplateServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateBlockRepositoryInterface $templateBlockRepository,
        private readonly TemplatePublishingService $templatePublishingService,
        private readonly TemplateReviewService $templateReviewService,
        private readonly TemplateReviewerAssignmentService $templateReviewerAssignmentService,
    ) {}

    /**
     * Canónico: devuelve el DTO de la plantilla.
     */
    public function findOrFail(string $id): TemplateDto
    {
        return TemplateDto::fromModel($this->templateRepository->findOrFail($id));
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
     * Localiza una versión de plantilla por su ID.
     */
    public function findVersionOrFail(string $versionId): EntityVersion
    {
        return $this->templateVersionRepository->findOrFail($versionId);
    }

    /**
     * Localiza una versión polimórfica por su ID.
     */
    public function findEntityVersionOrFail(string $versionId): EntityVersion
    {
        return $this->entityVersionRepository->findOrFail($versionId);
    }

    /**
     * Envía el borrador a revisión. Solo el creador de la plantilla puede ejecutar esta acción.
     *
     * - Sin revisores asignados → publica automáticamente.
     * - Con revisores → resetea sus estados a `pending` (necesario para rondas
     *   sucesivas: en draft post-rechazo los estados quedan visibles para el autor,
     *   y solo se limpian al reenviar) y transiciona a `in_review`.
     */
    public function submitForReview(string $templateId, string $actorId): Template
    {
        return $this->templateReviewService->submitForReview($templateId, $actorId);
    }

    /**
     * Rechaza la revisión de la plantilla.
     *
     * Registra el rechazo del actor en `template_reviewers` (auditoría de quién rechazó)
     * y transiciona la plantilla a borrador. Los estados quedan visibles en draft
     * para que el autor sepa quién rechazó; se limpian al reenviar.
     */
    public function rejectReview(string $templateId, string $actorId): Template
    {
        return $this->templateReviewService->rejectReview($templateId, $actorId);
    }

    /**
     * Registra la aprobación del revisor activo.
     *
     * En modo secuencial exige que todos los stages anteriores estén aprobados.
     * Si tras esta aprobación todos los revisores están en `approved`, publica
     * la plantilla automáticamente con un snapshot.
     */
    public function approveReview(string $templateId, string $actorId): Template
    {
        return $this->templateReviewService->approveReview($templateId, $actorId);
    }

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId): Template
    {
        return $this->templatePublishingService->publishWithSnapshot($templateId, $changelog, $actorId);
    }

    /**
     * Lista todas las versiones publicadas de una plantilla ordenadas por número de versión.
     *
     * @return Collection<int, EntityVersion>
     */
    public function listPublishedVersions(string $templateId): Collection
    {
        return $this->entityVersionRepository->listPublishedForEntityOrdered(Template::class, $templateId);
    }

    /**
     * Listado con filtros (sin paginación en servidor; el front pagina en cliente).
     * Enriquece cada plantilla con metadatos de la última versión publicada para el API.
     */
    public function listFiltered(FilterTemplatesDto $filters): Collection
    {
        $templates = $this->templateRepository->listFiltered($filters);
        $this->templateRepository->attachLatestPublishedVersionMeta($templates);

        return $templates;
    }

    /**
     * Crea una plantilla con los atributos dados.
     */
    public function create(CreateTemplateDto $dto): Template
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('Cannot create template without authenticated user.');
        }
        $this->assertTemplateMetadataInvariants(
            $dto->name,
            $dto->deliveryDeadline,
            $dto->visibilityLevel,
        );

        return $this->templateRepository->create([
            'process_id' => $dto->processId,
            'name' => $dto->name,
            'description' => $dto->description,
            'visibility_level' => $dto->visibilityLevel,
            'delivery_deadline' => $dto->deliveryDeadline,
            'study_type_id' => $dto->studyTypeId,
            'study_id' => $dto->studyId,
            'module_id' => $dto->moduleId,
            'team_id' => $dto->teamId,
            'created_by' => (string) $userId,
            'status' => 'draft',
            'review_stages' => $dto->reviewStages,
            'review_mode' => $dto->reviewMode,
        ]);
    }

    /**
     * Actualiza una plantilla con los atributos dados.
     * Recibe el modelo ya resuelto para evitar una query redundante.
     */
    public function update(Template $template, UpdateTemplateDto $dto): Template
    {
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
        $this->assertTemplateMetadataInvariants(
            (string) ($attributes['name'] ?? $template->name),
            $attributes['delivery_deadline'] ?? $template->delivery_deadline,
            $attributes['visibility_level'] ?? $template->visibility_level,
        );

        return $this->templateRepository->update($template, $attributes);
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
            $template->loadMissing('headVersion');
            $head = $template->headVersion;

            if ($head !== null && (int) $head->version_number === 0 && in_array((string) $head->status, ['draft', 'in_review'], true)) {
                // Published version exists + working draft → discard draft, restore published.
                $this->destroyVersion($templateId, (string) $head->id, $actorId);
                return false;
            }

            throw ValidationException::withMessages([
                'template' => ['No se puede eliminar una plantilla publicada sin versión de trabajo activa.'],
            ]);
        }

        if ($this->templateRepository->templateHasDocuments($templateId)) {
            if ($template->status !== 'archived') {
                $this->templatePublishingService->transitionStatus($template, 'archived', $actorId);
            }

            return false;
        }

        $template->delete();

        return true;
    }

    /**
     * Clona una plantilla origen hacia una nueva en borrador.
     *
     * Si existe versión publicada en {@see EntityVersion}, la copia se materializa desde ese
     * snapshot; si no, desde bloques y revisores vivos.
     */
    public function clone(string $sourceTemplateId, string $actorId): Template
    {
        $source = $this->templateRepository->findOrFail($sourceTemplateId);
        $this->assertTemplateMetadataInvariants(
            (string) $source->name,
            $source->delivery_deadline,
            $source->visibility_level,
        );

        $published = $this->resolveLatestPublishedTemplateSnapshotForClone((string) $source->id);
        if ($published !== null) {
            return $this->cloneTemplateFromPublishedSnapshot($source, $published, $actorId);
        }

        return $this->cloneTemplateFromLiveSource($source, $actorId);
    }

    /**
     * Transición explícita publicada → borrador para preparar la siguiente versión publicada.
     */
    public function startNewRevisionCycle(string $templateId, string $actorId): Template
    {
        $template = $this->templateRepository->findOrFail($templateId);

        if ($template->status !== 'published') {
            throw ValidationException::withMessages([
                'status' => ['Solo una plantilla publicada puede pasar a borrador para una nueva versión.'],
            ]);
        }
        $this->assertTemplateMetadataInvariants(
            (string) $template->name,
            $template->delivery_deadline,
            $template->visibility_level,
        );

        return $this->templatePublishingService->transitionStatus(
            $template,
            'draft',
            $actorId,
            ['created_by' => $actorId],
        );
    }

    /**
     * Descarta la versión de trabajo actual (head mutable) y restaura snapshot/revisores de la última publicación.
     */
    public function destroyVersion(string $templateId, string $versionId, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($templateId, $versionId) {
            $template = $this->templateRepository->findOrFail($templateId);
            $template->loadMissing('headVersion');
            $head = $template->headVersion;

            if ($head === null || (string) $head->id !== $versionId || (int) $head->version_number !== 0) {
                throw ValidationException::withMessages([
                    'version' => ['Solo se puede descartar la versión de trabajo actual de la plantilla.'],
                ]);
            }

            if (! in_array((string) $head->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'version' => ['Solo se pueden descartar versiones no publicadas (draft/in_review).'],
                ]);
            }

            $latestPublished = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
            if ($latestPublished === null || ! is_array($latestPublished->snapshot_data)) {
                throw ValidationException::withMessages([
                    'version' => ['No existe una versión publicada a la que restaurar.'],
                ]);
            }

            $publishedSnapshot = $latestPublished->snapshot_data;
            $publishedTemplate = isset($publishedSnapshot['template']) && is_array($publishedSnapshot['template'])
                ? $publishedSnapshot['template']
                : [];

            $head->snapshot_data = $publishedSnapshot;
            $head->status = 'published';
            $head->updated_at = now();
            $head->save();

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

            return $template->loadMissing(['reviewers', 'documentReviewers']);
        });
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

        $blockIdsInUseByDocuments = DB::table('document_blocks')
            ->whereIn('template_block_id', function ($query) use ($templateId) {
                $query->select('id')
                    ->from('template_blocks')
                    ->where('template_id', $templateId);
            })
            ->pluck('template_block_id')
            ->map(static fn ($id) => (string) $id)
            ->all();

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

        $template->reviewers()->withTrashed()->forceDelete();
        foreach ($templateReviewers as $row) {
            if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                continue;
            }
            $template->reviewers()->create([
                'user_id' => $row['user_id'],
                'stage' => isset($row['stage']) ? (int) $row['stage'] : 1,
                'status' => 'pending',
            ]);
        }

        $template->documentReviewers()->delete();
        foreach ($documentReviewers as $row) {
            if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                continue;
            }
            $template->documentReviewers()->create([
                'user_id' => $row['user_id'],
            ]);
        }
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
            $cloneName = $nameBase.' (copia)';
            $this->assertTemplateMetadataInvariants(
                $cloneName,
                $cloneDeliveryDeadline,
                $cloneVisibility,
            );

            $target = $this->templateRepository->create([
                'process_id' => $source->process_id,
                'name' => $cloneName,
                'description' => $this->cloneTemplateDescription($kind, $templateMeta, $source),
                'visibility_level' => $cloneVisibility,
                'delivery_deadline' => $cloneDeliveryDeadline,
                'study_type_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'study_type_id'),
                'study_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'study_id'),
                'module_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'module_id'),
                'team_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'team_id'),
                'created_by' => $actorId,
                'status' => 'draft',
                'review_stages' => $this->cloneTemplateReviewStages($kind, $templateMeta, $source),
                'review_mode' => $this->cloneTemplateReviewMode($kind, $templateMeta, $source),
            ]);

            $this->templateRepository->insertBlocksFromPublishedSnapshot((string) $target->getKey(), $published['blocks']);

            if ($published['reviewers_from_snapshot'] ?? false) {
                foreach ($published['template_reviewers'] as $row) {
                    if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                        continue;
                    }
                    $target->reviewers()->create([
                        'user_id' => $row['user_id'],
                        'stage' => isset($row['stage']) ? (int) $row['stage'] : 1,
                        'status' => 'pending',
                    ]);
                }
                foreach ($published['document_reviewers'] as $row) {
                    if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                        continue;
                    }
                    $target->documentReviewers()->create([
                        'user_id' => $row['user_id'],
                    ]);
                }
            } else {
                $source->loadMissing(['reviewers', 'documentReviewers']);
                foreach ($source->reviewers as $reviewer) {
                    $target->reviewers()->create([
                        'user_id' => $reviewer->user_id,
                        'stage' => $reviewer->stage,
                        'status' => 'pending',
                    ]);
                }
                foreach ($source->documentReviewers as $docReviewer) {
                    $target->documentReviewers()->create([
                        'user_id' => $docReviewer->user_id,
                    ]);
                }
            }

            return $this->templateRepository->findOrFail($target->getKey());
        });
    }

    private function cloneTemplateFromLiveSource(Template $source, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($source, $actorId) {
            $source->loadMissing(['blocks', 'reviewers', 'documentReviewers']);
            $cloneVisibility = $source->visibility_level instanceof TemplateVisibilityLevel
                ? $source->visibility_level->value
                : $source->visibility_level;
            $cloneName = $source->name.' (copia)';
            $this->assertTemplateMetadataInvariants(
                $cloneName,
                $source->delivery_deadline,
                $cloneVisibility,
            );

            $target = $this->templateRepository->create([
                'process_id' => $source->process_id,
                'name' => $cloneName,
                'description' => $source->description,
                'visibility_level' => $cloneVisibility,
                'delivery_deadline' => $source->delivery_deadline,
                'study_type_id' => $source->study_type_id,
                'study_id' => $source->study_id,
                'module_id' => $source->module_id,
                'team_id' => $source->team_id,
                'created_by' => $actorId,
                'status' => 'draft',
                'review_stages' => $source->review_stages,
                'review_mode' => $source->review_mode,
            ]);

            $this->templateRepository->replicateBlocks($source, $target);

            foreach ($source->reviewers as $reviewer) {
                $target->reviewers()->create([
                    'user_id' => $reviewer->user_id,
                    'stage' => $reviewer->stage,
                ]);
            }

            foreach ($source->documentReviewers as $docReviewer) {
                $target->documentReviewers()->create([
                    'user_id' => $docReviewer->user_id,
                ]);
            }

            return $this->templateRepository->findOrFail($target->getKey());
        });
    }

    /**
     * Resuelve la última versión publicada con metadatos si el ganador por meta no produce snapshot usable.
     * 
     * @param  string  $templateId
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
     * @param  string  $kind
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
     * @param  string  $kind
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
     * Clona el valor de un FK de la plantilla.
     * 
     * @param  string  $key
     * @return mixed
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
     * @return string
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
     * @param  string  $name
     * @param  Carbon|string|null  $deliveryDeadline
     * @param  TemplateVisibilityLevel|string  $visibilityLevel
     */
    private function assertTemplateMetadataInvariants(string $name, mixed $deliveryDeadline, mixed $visibilityLevel): void
    {
        if (trim($name) === '') {
            throw ValidationException::withMessages([
                'name' => ['El nombre de la plantilla es obligatorio.'],
            ]);
        }

        if ($deliveryDeadline === null || (is_string($deliveryDeadline) && trim($deliveryDeadline) === '')) {
            throw ValidationException::withMessages([
                'delivery_deadline' => ['La fecha de entrega de la plantilla es obligatoria.'],
            ]);
        }

        $normalizedVisibility = $visibilityLevel instanceof TemplateVisibilityLevel
            ? $visibilityLevel->value
            : (is_string($visibilityLevel) ? trim($visibilityLevel) : '');
        if ($normalizedVisibility === '') {
            throw ValidationException::withMessages([
                'visibility_level' => ['La visibilidad de la plantilla es obligatoria.'],
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
        $normalized = $attributes;
        $visibility = $this->normalizeTemplateVisibilityLevelValue($template->visibility_level);

        $templateStudyTypeId = is_string($template->study_type_id) && $template->study_type_id !== '' ? $template->study_type_id : null;
        $templateStudyId = is_string($template->study_id) && $template->study_id !== '' ? $template->study_id : null;
        $templateModuleId = is_string($template->module_id) && $template->module_id !== '' ? $template->module_id : null;

        $studyTypeId = array_key_exists('study_type_id', $attributes) ? $attributes['study_type_id'] : $template->study_type_id;
        $studyId = array_key_exists('study_id', $attributes) ? $attributes['study_id'] : $template->study_id;
        $moduleId = array_key_exists('module_id', $attributes) ? $attributes['module_id'] : $template->module_id;

        if ($visibility === TemplateVisibilityLevel::Personal->value || $visibility === TemplateVisibilityLevel::Team->value) {
            $normalized['study_type_id'] = $templateStudyTypeId;
            $normalized['study_id'] = $templateStudyId;
            $normalized['module_id'] = $templateModuleId;

            return $normalized;
        }

        if ($visibility === TemplateVisibilityLevel::Module->value) {
            if ($templateModuleId !== null && $moduleId !== null && $moduleId !== $templateModuleId) {
                throw ValidationException::withMessages([
                    'module_id' => ['La plantilla debe mantenerse en el mismo módulo.'],
                ]);
            }

            $normalized['module_id'] = $templateModuleId;
            $normalized['study_id'] = $templateStudyId;
            $normalized['study_type_id'] = $templateStudyTypeId;

            return $normalized;
        }

        if ($visibility === TemplateVisibilityLevel::Study->value) {
            if ($templateStudyId !== null && $studyId !== null && $studyId !== $templateStudyId) {
                throw ValidationException::withMessages([
                    'study_id' => ['La plantilla debe mantenerse en el mismo estudio.'],
                ]);
            }

            $normalized['study_id'] = $templateStudyId;
            $normalized['study_type_id'] = $templateStudyTypeId;

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

            return $normalized;
        }

        if ($visibility === TemplateVisibilityLevel::StudyType->value) {
            $normalized['study_type_id'] = $templateStudyTypeId;

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

        if ($visibility === TemplateVisibilityLevel::Global->value && is_string($moduleId) && $moduleId !== '') {
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

        return $normalized;
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

}
