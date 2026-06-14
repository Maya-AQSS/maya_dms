<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Models\Document;
use App\Services\DocumentTemplateVersionNumberResolver;
use App\Support\ApiEmbeddedTeamResponse;
use App\Support\IsoTimestamp;
use App\Support\VersionSubmissionChangelog;

final readonly class DocumentDto
{
    /**
     * @param  array<string, mixed>|null  $team
     */
    public function __construct(
        public string $id,
        public ?string $processId,
        public ?string $templateId,
        public ?string $templateVersionId,
        public ?int $templateVersionNumber,
        public ?string $templateName,
        public ?array $team,
        public ?string $title,
        public ?string $studyTypeId,
        public ?string $studyId,
        public ?string $moduleId,
        public ?string $teamId,
        public ?string $deliveryDeadline,
        public ?string $createdBy,
        public ?string $ownerId,
        public ?string $ownerName,
        public ?string $visibilityLevel,
        public ?string $status,
        public ?int $currentVersion,
        public ?string $submittedAt,
        public ?string $publishedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
        public bool $isSharedWithMe,
        public ?string $sharePermission,
        public bool $canClone,
        public bool $canViewHistory,
        public bool $canCreateNewVersion,
        public ?string $workingVersionId,
        public ?string $latestPublishedVersionId,
        public ?int $latestPublishedVersionNumber,
        public ?string $latestPublishedTitle,
        public ?string $reviewMode,
        public bool $isAssignedReviewer,
        public ?array $reviewHistory,
        public ?string $submissionChangelog = null,
        public bool $workingRevisionInProgress = false,
        public ?string $workingRevisionEditorName = null,
        public ?string $workingRevisionStartedAt = null,
    ) {}

    public static function fromModel(Document $m): self
    {
        $team = $m->getAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY);
        $ownerName = $m->getAttribute('owner_name')
            ?? ($m->relationLoaded('owner') ? $m->owner?->name : null);
        $visibility = $m->relationLoaded('template') && $m->template !== null
            ? $m->template->visibility_level->value
            : null;
        $templateVersionNumber = self::resolveTemplateVersionNumber($m);

        $reviewHistory = null;
        if ($m->relationLoaded('headVersion') && $m->headVersion !== null) {
            $snap = $m->headVersion->snapshot_data;
            if (is_array($snap) && isset($snap['blocks_at_previous_submission']) && is_array($snap['blocks_at_previous_submission'])) {
                $blocksAtPreviousSubmission = $snap['blocks_at_previous_submission'];
            }
            $changeSet = $m->headVersion->change_set;
            if (is_array($changeSet) && count($changeSet) > 0) {
                $reviewHistory = $changeSet;
            }
        }

        return new self(
            id: (string) $m->id,
            processId: $m->process_id !== null ? (string) $m->process_id : null,
            templateId: $m->template_id !== null ? (string) $m->template_id : null,
            templateVersionId: $m->template_version_id !== null ? (string) $m->template_version_id : null,
            templateVersionNumber: $templateVersionNumber,
            templateName: self::resolveTemplateName($m),
            team: is_array($team) ? $team : null,
            title: $m->title,
            studyTypeId: $m->study_type_id !== null ? (string) $m->study_type_id : null,
            studyId: $m->study_id !== null ? (string) $m->study_id : null,
            moduleId: $m->module_id !== null ? (string) $m->module_id : null,
            teamId: $m->team_id !== null ? (string) $m->team_id : null,
            deliveryDeadline: $m->delivery_deadline?->toIso8601String(),
            createdBy: $m->created_by !== null ? (string) $m->created_by : null,
            ownerId: $m->owner_id !== null ? (string) $m->owner_id : null,
            ownerName: $ownerName,
            visibilityLevel: $visibility,
            status: $m->status !== null ? (string) $m->status : null,
            currentVersion: $m->current_version !== null ? (int) $m->current_version : null,
            submittedAt: $m->submitted_at?->toIso8601String(),
            publishedAt: $m->published_at?->toIso8601String(),
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            isSharedWithMe: (bool) ($m->getAttribute('is_shared_with_me') ?? false),
            sharePermission: $m->getAttribute('viewer_share_permission'),
            canClone: (bool) ($m->getAttribute('can_clone') ?? false),
            canViewHistory: (bool) ($m->getAttribute('can_view_history') ?? false),
            canCreateNewVersion: (bool) ($m->getAttribute('can_create_new_version') ?? false),
            workingVersionId: $m->head_entity_version_id !== null ? (string) $m->head_entity_version_id : null,
            latestPublishedVersionId: $m->getAttribute('latest_published_version_id'),
            latestPublishedVersionNumber: $m->getAttribute('latest_published_version_number') !== null
                ? (int) $m->getAttribute('latest_published_version_number')
                : null,
            latestPublishedTitle: $m->getAttribute('latest_published_title'),
            reviewMode: $m->review_mode !== null ? (string) $m->review_mode : null,
            isAssignedReviewer: (bool) ($m->getAttribute('is_assigned_reviewer') ?? false),
            reviewHistory: $reviewHistory,
            submissionChangelog: self::submissionChangelogFrom($m),
            workingRevisionInProgress: (bool) ($m->getAttribute('working_revision_in_progress') ?? false),
            workingRevisionEditorName: $m->getAttribute('working_revision_editor_name'),
            workingRevisionStartedAt: IsoTimestamp::formatOptional($m->getAttribute('working_revision_started_at')),
        );
    }

    private static function submissionChangelogFrom(Document $m): ?string
    {
        $m->loadMissing('headVersion');

        return VersionSubmissionChangelog::forApiExposure(
            $m->status !== null ? (string) $m->status : null,
            $m->headVersion?->changelog,
        );
    }

    /**
     * Nombre de la plantilla en la versión con la que se creó el documento.
     *
     * Prioridad: atributo precargado `template_name` → relación `templateVersion`
     * (ya cargada en los caminos de detalle/listado, sin N+1) → fallback al
     * {@see DocumentTemplateVersionNumberResolver}, que resuelve a través de la capa
     * Repository (sin acceso a Eloquent dentro del DTO).
     */
    private static function resolveTemplateName(Document $m): ?string
    {
        $preloaded = $m->getAttribute('template_name');
        if (is_string($preloaded) && $preloaded !== '') {
            return $preloaded;
        }

        if ($m->template_version_id === null) {
            return null;
        }

        if ($m->relationLoaded('templateVersion') && $m->templateVersion !== null) {
            $snapshot = $m->templateVersion->snapshot_data;
            if (is_array($snapshot) && isset($snapshot['template']['name']) && is_string($snapshot['template']['name'])) {
                $name = $snapshot['template']['name'];

                return $name !== '' ? $name : null;
            }

            return null;
        }

        return app(DocumentTemplateVersionNumberResolver::class)
            ->resolveName((string) $m->template_version_id);
    }

    private static function resolveTemplateVersionNumber(Document $m): ?int
    {
        $preloaded = $m->getAttribute('template_version_number');
        if (is_numeric($preloaded)) {
            return (int) $preloaded;
        }

        if ($m->template_version_id === null) {
            return null;
        }

        if ($m->relationLoaded('templateVersion') && $m->templateVersion !== null) {
            return (int) $m->templateVersion->version_number;
        }

        return app(DocumentTemplateVersionNumberResolver::class)->resolve(
            $m->template_id !== null ? (string) $m->template_id : null,
            (string) $m->template_version_id,
        );
    }
}
