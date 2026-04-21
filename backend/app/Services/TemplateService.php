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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TemplateService implements TemplateServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
    ) {}

    /**
     * Localiza una plantilla por su ID.
     */
    public function findOrFail(string $id): Template
    {
        return $this->templateRepository->findOrFail($id);
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
        $template = $this->templateRepository->findOrFail($templateId);

        if ($template->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo las plantillas en borrador pueden enviarse a revisión.'],
            ]);
        }

        if ($template->reviewers()->doesntExist()) {
            return $this->publishWithSnapshot($templateId, null, $actorId);
        }

        $template->reviewers()->update(['status' => 'pending']);

        return $this->updateTemplateStatusWithEvent($template, 'in_review', $actorId);
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
        $template = $this->templateRepository->findOrFail($templateId);

        if ($template->status !== 'in_review') {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede rechazar una plantilla en revisión.'],
            ]);
        }

        $template->reviewers()
            ->where('user_id', $actorId)
            ->update(['status' => 'rejected']);

        return $this->updateTemplateStatusWithEvent($template, 'draft', $actorId);
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
        return DB::transaction(function () use ($templateId, $actorId) {
            $template = Template::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();

            if ($template->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => ['Solo se puede aprobar una plantilla en revisión.'],
                ]);
            }

            $reviewer = $template->reviewers()
                ->where('user_id', $actorId)
                ->first();

            if (! $reviewer) {
                throw ValidationException::withMessages([
                    'user' => ['No estás asignado como revisor de esta plantilla.'],
                ]);
            }

            if ($reviewer->status === 'approved') {
                throw ValidationException::withMessages([
                    'status' => ['Ya has aprobado esta plantilla.'],
                ]);
            }

            if ($template->review_mode === 'sequential') {
                $pendingPreviousStage = $template->reviewers()
                    ->where('stage', '<', $reviewer->stage)
                    ->where('status', '!=', 'approved')
                    ->exists();

                if ($pendingPreviousStage) {
                    throw ValidationException::withMessages([
                        'stage' => ['Debes esperar a que los revisores de etapas anteriores aprueben primero.'],
                    ]);
                }
            }

            $template->reviewers()
                ->where('user_id', $actorId)
                ->update(['status' => 'approved']);

            $allApproved = $template->reviewers()
                ->where('status', '!=', 'approved')
                ->doesntExist();

            if ($allApproved) {
                return $this->publishWithSnapshot($templateId, 'Aprobado por todos los revisores.', $actorId);
            }

            return $template->fresh();
        });
    }

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId): Template
    {
        return DB::transaction(function () use ($templateId, $changelog, $actorId) {
            /** @var Template $template */
            $template = Template::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();

            if (! in_array($template->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Solo se puede publicar una plantilla en borrador o en revisión.'],
                ]);
            }

            $template->load(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

            $blocksSnapshot = $template->blocks->map(fn ($b) => [
                'id' => $b->id,
                'type' => $b->type,
                'title' => $b->title,
                'description' => $b->description,
                'default_content' => $b->default_content,
                'block_state' => $b->block_state,
                'mandatory' => $b->mandatory,
                'sort_order' => $b->sort_order,
            ])->values()->all();

            $next = $this->templateVersionRepository->nextVersionNumber($templateId);

            $this->templateVersionRepository->createSnapshot(
                $templateId,
                $next,
                $blocksSnapshot,
                $changelog ?? '',
                $actorId,
            );

            $oldStatus = $template->status;
            $updated = $this->templateRepository->update($template, [
                'status' => 'published',
                'version' => $next,
            ]);

            event(new TemplateStateChanged(
                template: $updated,
                oldStatus: $oldStatus,
                newStatus: 'published',
                actorId: $actorId,
            ));

            return $updated;
        });
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
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 20): LengthAwarePaginator
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
            'name' => $source->name.' (copia)',
            'description' => $source->description,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $actorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => $source->review_stages,
            'review_mode' => $source->review_mode,
        ]);

        $this->templateRepository->replicateBlocks($source, $target);

        return $this->templateRepository->findOrFail($target->getKey());
    }

    /**
     * Actualiza solo el estado de la plantilla vía repositorio y emite {@see TemplateStateChanged}.
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

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(string $templateId, array $userIds): void
    {
        DB::transaction(function () use ($templateId, $userIds) {
            $template = $this->templateRepository->findOrFail($templateId);

            $template->reviewers()->delete();

            foreach ($userIds as $index => $userId) {
                $template->reviewers()->create([
                    'user_id' => $userId,
                    'stage'   => $index + 1,
                ]);
            }
        });
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(string $templateId, array $userIds): void
    {
        DB::transaction(function () use ($templateId, $userIds) {
            $template = $this->templateRepository->findOrFail($templateId);

            $template->documentReviewers()->delete();

            foreach ($userIds as $userId) {
                $template->documentReviewers()->create([
                    'user_id' => $userId,
                ]);
            }
        });
    }
}
