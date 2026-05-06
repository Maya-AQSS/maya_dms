<?php

namespace App\Services;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\SyncUsersDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\Enums\TemplateVisibilityLevel;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TemplateService implements TemplateServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplatePublishingService $templatePublishingService,
        private readonly TemplateReviewService $templateReviewService,
        private readonly TemplateReviewerAssignmentService $templateReviewerAssignmentService,
    ) {}

    /**
     * Localiza una plantilla por su ID.
     */
    public function findOrFail(string $id): Template
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
     */
    public function listFiltered(FilterTemplatesDto $filters): Collection
    {
        return $this->templateRepository->listFiltered($filters);
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

        return $this->templatePublishingService->transitionStatus($template, 'draft', $actorId);
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

            $target = $this->templateRepository->create([
                'process_id' => $source->process_id,
                'name' => $nameBase.' (copia)',
                'description' => $this->cloneTemplateDescription($kind, $templateMeta, $source),
                'visibility_level' => $this->normalizeTemplateVisibilityLevelForClone($kind, $templateMeta, $source),
                'delivery_deadline' => $source->delivery_deadline,
                'study_type_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'study_type_id'),
                'study_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'study_id'),
                'module_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'module_id'),
                'team_id' => $this->cloneTemplateNullableFk($kind, $templateMeta, $source, 'team_id'),
                'created_by' => $actorId,
                'status' => 'draft',
                'review_stages' => $source->review_stages,
                'review_mode' => $source->review_mode,
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

            $target = $this->templateRepository->create([
                'process_id' => $source->process_id,
                'name' => $source->name.' (copia)',
                'description' => $source->description,
                'visibility_level' => $source->visibility_level instanceof TemplateVisibilityLevel
                    ? $source->visibility_level->value
                    : $source->visibility_level,
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
    private function cloneTemplateNullableFk(string $kind, array $templateMeta, Template $source, string $key): mixed
    {
        if ($kind === 'entity' && array_key_exists($key, $templateMeta)) {
            return $templateMeta[$key];
        }

        return $source->{$key};
    }

    /**
     * @param  array<string, mixed>  $templateMeta
     */
    private function normalizeTemplateVisibilityLevelForClone(string $kind, array $templateMeta, Template $source): string
    {
        if ($kind === 'entity' && array_key_exists('visibility_level', $templateMeta)) {
            return $this->normalizeTemplateVisibilityLevelValue($templateMeta['visibility_level']);
        }

        return $this->normalizeTemplateVisibilityLevelValue($source->visibility_level);
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
