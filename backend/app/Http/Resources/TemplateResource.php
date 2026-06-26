<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Templates\TemplateDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TemplateDto $resource
 */
class TemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TemplateDto $dto */
        $dto = $this->resource;

        $payload = [
            'id' => $dto->id,
            'name' => $dto->name,
            'description' => $dto->description,
            'visibility_level' => $dto->visibilityLevel,
            'delivery_deadline' => $dto->deliveryDeadline,
            'document_delivery_deadline' => $dto->documentDeliveryDeadline,
            'study_type_id' => $dto->studyTypeId,
            'study_id' => $dto->studyId,
            'module_id' => $dto->moduleId,
            'process_id' => $dto->processId,
            'team_id' => $dto->teamId,
            'team' => $dto->team,
            'theme_id' => $dto->themeId,
            'theme' => $dto->themeMini,
            'created_by' => $dto->createdBy,
            'author_name' => $dto->authorName,
            'status' => $dto->status,
            'version' => $dto->version,
            'review_stages' => $dto->reviewStages,
            'review_mode' => $dto->reviewMode,
            'document_review_mode' => $dto->documentReviewMode,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
            'has_review_comments' => $dto->hasReviewComments,
            'has_unread_review_comments' => $dto->hasUnreadReviewComments,
            'latest_published_version_id' => $dto->latestPublishedVersionId,
            'latest_published_version_number' => $dto->latestPublishedVersionNumber,
            'can_clone' => $dto->canClone,
            'can_view_history' => $dto->canViewHistory,
            'can_create_new_version' => $dto->canCreateNewVersion,
            'working_version_id' => $dto->workingVersionId,
            'latest_published_name' => $dto->latestPublishedName,
            'latest_published_at' => $dto->latestPublishedAt,
            'blocks_at_previous_submission' => $dto->blocksAtPreviousSubmission,
            'review_history' => $dto->reviewHistory,
            'submission_changelog' => $dto->submissionChangelog,
            'working_revision_in_progress' => $dto->workingRevisionInProgress,
            'working_revision_editor_name' => $dto->workingRevisionEditorName,
            'working_revision_started_at' => $dto->workingRevisionStartedAt,
        ];

        if ($dto->reviewersLoaded) {
            $payload['reviewers'] = $dto->reviewers;
        }

        if ($dto->documentReviewersLoaded) {
            $payload['document_reviewers'] = $dto->documentReviewers;
            $payload['document_reviewer_users'] = $dto->documentReviewerUsers;
        }

        return $payload;
    }
}
