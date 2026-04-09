<?php

namespace App\Services;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\Enums\TemplateVisibilityLevel;
use App\Events\TemplateStateChanged;
use App\Models\JwtUser;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class TemplateService implements TemplateServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * Localiza una plantilla por su ID.
     */
    public function findOrFail(string $id): Template
    {
        return $this->templateRepository->findOrFail($id);
    }

    /**
     * Transiciona la plantilla a un nuevo estado y emite el evento de dominio TemplateStateChanged.
     */
    public function transition(string $templateId, string $newStatus, string $actorId): Template
    {
        $template  = $this->templateRepository->findOrFail($templateId);
        $oldStatus = $template->status;

        $template->update(['status' => $newStatus]);

        event(new TemplateStateChanged(
            template:  $template,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId:   $actorId,
        ));

        return $template;
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
     * 
     * @param  CreateTemplateDto  $dto
     */
    public function create(CreateTemplateDto $dto): Template
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('Cannot create template without authenticated user.');
        }

        $user = Auth::user();
        $organizationId = $dto->organizationId;
        if ($organizationId === null && $user instanceof JwtUser) {
            $organizationId = $user->organizationId;
        }

        return $this->templateRepository->create([
            'name'               => $dto->name,
            'description'        => $dto->description,
            'visibility_level'   => $dto->visibilityLevel,
            'delivery_deadline'  => $dto->deliveryDeadline,
            'study_type_id'      => $dto->studyTypeId,
            'study_id'           => $dto->studyId,
            'module_id'          => $dto->moduleId,
            'group_id'           => $dto->groupId,
            'organization_id'    => $organizationId,
            'created_by'         => (string) $userId,
            'status'             => 'draft',
            'version'            => 1,
            'review_stages'      => $dto->reviewStages,
            'review_mode'        => $dto->reviewMode,
        ]);
    }

    /**
     * Actualiza una plantilla con los atributos dados.
     * 
     * @param  string  $templateId
     * @param  UpdateTemplateDto  $dto
     */
    public function update(string $templateId, UpdateTemplateDto $dto): Template
    {
        $template = $this->templateRepository->findOrFail($templateId);

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
        if ($dto->setGroupId) {
            $attributes['group_id'] = $dto->groupId;
        }
        if ($dto->setOrganizationId) {
            $attributes['organization_id'] = $dto->organizationId;
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
     * @param  string  $templateId
     * @param  string  $actorId
     * @return bool true si se eliminó físicamente; false si solo se archivó (hay documentos asociados).
     */
    public function destroy(string $templateId, string $actorId): bool
    {
        $template = $this->templateRepository->findOrFail($templateId);

        if ($this->templateRepository->templateHasDocuments($templateId)) {
            if ($template->status !== 'archived') {
                $oldStatus = $template->status;
                $this->templateRepository->update($template, ['status' => 'archived']);
                $fresh = $this->templateRepository->findOrFail($templateId);
                event(new TemplateStateChanged(
                    template:  $fresh,
                    oldStatus: $oldStatus,
                    newStatus: 'archived',
                    actorId:   $actorId,
                ));
            }

            return false;
        }

        $template->forceDelete();

        return true;
    }

    /**
     * Clona una plantilla origen hacia una nueva destino.
     * 
     * @param  string  $sourceTemplateId
     * @param  string  $actorId
     */
    public function clone(string $sourceTemplateId, string $actorId): Template
    {
        $source = $this->templateRepository->findOrFail($sourceTemplateId);
        $source->loadMissing('blocks');

        $user  = Auth::user();
        $orgId = $user instanceof JwtUser ? $user->organizationId : null;

        $target = $this->templateRepository->create([
            'name'               => $source->name.' (copia)',
            'description'        => $source->description,
            'visibility_level'   => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline'  => null,
            'study_type_id'      => null,
            'study_id'           => null,
            'module_id'          => null,
            'group_id'           => null,
            'organization_id'    => $orgId,
            'created_by'         => $actorId,
            'status'             => 'draft',
            'version'            => 1,
            'review_stages'      => $source->review_stages,
            'review_mode'        => $source->review_mode,
        ]);

        $this->templateRepository->replicateBlocks($source, $target);

        return $this->templateRepository->findOrFail($target->getKey());
    }
}
