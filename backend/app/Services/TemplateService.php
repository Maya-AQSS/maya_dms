<?php

namespace App\Services;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\Enums\TemplateVisibilityLevel;
use App\Events\TemplateStateChanged;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TemplateService implements TemplateServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
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
     * Localiza una plantilla por su ID sin el global scope de catálogo `user_access`.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template
    {
        return $this->templateRepository->findOrFailWithoutCatalogScope($id);
    }

    /**
     * Localiza una versión de plantilla por su ID.
     */
    public function findVersionOrFail(string $versionId): TemplateVersion
    {
        return $this->templateVersionRepository->findOrFail($versionId);
    }

    /**
     * Transiciona la plantilla a un nuevo estado y emite el evento de dominio TemplateStateChanged.
     */
    public function transition(string $templateId, string $newStatus, string $actorId): Template
    {
        $template = $this->templateRepository->findOrFail($templateId);

        return $this->updateTemplateStatusWithEvent($template, $newStatus, $actorId);
    }

    /**
     * Envia el borrador a revisión (autor o quien puede editar la plantilla).
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
     * @return Collection<int, TemplateVersion>
     */
    public function listPublishedVersions(string $templateId): Collection
    {
        return $this->templateVersionRepository->listForTemplateOrdered($templateId);
    }

    /**
     * Listado paginado con filtros (20 ítems por defecto en request).
     */
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->templateRepository->paginateFiltered($filters, $perPage);
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
            'version' => 1,
            'review_stages' => $dto->reviewStages,
            'review_mode' => $dto->reviewMode,
        ]);
    }

    /**
     * Actualiza una plantilla con los atributos dados.
     */
    public function update(string $templateId, UpdateTemplateDto $dto): Template
    {
        $template = $this->templateRepository->findOrFail($templateId);

        if ($dto->setStatus && $dto->status === 'published') {
            throw ValidationException::withMessages([
                'status' => ['La publicación se realiza con POST /api/v1/templates/{id}/publish e incluye changelog obligatorio.'],
            ]);
        }

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
        if ($dto->setStatus) {
            $attributes['status'] = $dto->status;
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
     * Elimina una plantilla físicamente (no se archiva).
     *
     * @return bool true si se eliminó físicamente; false si solo se archivó (hay documentos asociados).
     */
    public function destroy(string $templateId, string $actorId): bool
    {
        $template = $this->templateRepository->findOrFail($templateId);

        if ($this->templateRepository->templateHasDocuments($templateId)) {
            if ($template->status !== 'archived') {
                $this->updateTemplateStatusWithEvent($template, 'archived', $actorId);
            }

            return false;
        }

        $template->forceDelete();

        return true;
    }

    /**
     * Clona una plantilla origen hacia una nueva destino.
     */
    public function clone(string $sourceTemplateId, string $actorId): Template
    {
        $source = $this->templateRepository->findOrFail($sourceTemplateId);
        $source->loadMissing('blocks');

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
            'version' => ((int) $source->version) + 1,
            'review_stages' => $source->review_stages,
            'review_mode' => $source->review_mode,
        ]);

        $this->templateRepository->replicateBlocks($source, $target);

        return $this->templateRepository->findOrFail($target->getKey());
    }

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(string $templateId, array $userIds): void
    {
        $this->templateReviewerAssignmentService->syncReviewers($templateId, $userIds);
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(string $templateId, array $userIds): void
    {
        $this->templateReviewerAssignmentService->syncDocumentReviewers($templateId, $userIds);
    }

    /**
     * Actualiza estado de plantilla y emite evento de cambio.
     */
    private function updateTemplateStatusWithEvent(Template $template, string $newStatus, string $actorId): Template
    {
        $oldStatus = $template->status;
        $updated = $this->templateRepository->update($template, ['status' => $newStatus]);

        event(new TemplateStateChanged(
            template: $updated,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId: $actorId,
        ));

        return $updated;
    }
}
