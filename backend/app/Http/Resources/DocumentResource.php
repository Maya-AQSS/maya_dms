<?php
declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\DocumentDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DocumentDto $resource
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DocumentDto $dto */
        $dto = $this->resource;

        return [
            'id' => $dto->id,
            'process_id' => $dto->processId,
            'template_id' => $dto->templateId,
            'template_version_id' => $dto->templateVersionId,
            'template_version_number' => $dto->templateVersionNumber,
            'team' => $dto->team,
            'title' => $dto->title,
            'study_type_id' => $dto->studyTypeId,
            'study_id' => $dto->studyId,
            'module_id' => $dto->moduleId,
            'team_id' => $dto->teamId,
            'delivery_deadline' => $dto->deliveryDeadline,
            'created_by' => $dto->createdBy,
            'owner_id' => $dto->ownerId,
            'owner_name' => $dto->ownerName,
            'visibility_level' => $dto->visibilityLevel,
            'status' => $dto->status,
            'current_version' => $dto->currentVersion,
            'submitted_at' => $dto->submittedAt,
            'published_at' => $dto->publishedAt,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
            'is_shared_with_me' => $dto->isSharedWithMe,
            'share_permission' => $dto->sharePermission,
            'can_clone' => $dto->canClone,
            'working_version_id' => $dto->workingVersionId,
            'latest_published_version_id' => $dto->latestPublishedVersionId,
            'latest_published_version_number' => $dto->latestPublishedVersionNumber,
            'latest_published_title' => $dto->latestPublishedTitle,
        ];
    }
}
