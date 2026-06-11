<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

use App\Models\Template;
use App\Support\ApiEmbeddedTeamResponse;
use App\Support\IsoTimestamp;
use App\Support\TemplateHeadSnapshot;
use App\Support\VersionSubmissionChangelog;

final readonly class TemplateDto
{
    /**
     * @param  array<string, mixed>|null  $team
     * @param  list<array{user_id: string, user_name: ?string, stage: int|null, status: string|null}>|null  $reviewers
     * @param  list<string>|null  $documentReviewers
     * @param  list<array{user_id: string, user_name: ?string, stage?: int|null}>|null  $documentReviewerUsers
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $description,
        public ?string $visibilityLevel,
        public ?string $deliveryDeadline,
        public ?string $studyTypeId,
        public ?string $studyId,
        public ?string $moduleId,
        public ?string $processId,
        public ?string $teamId,
        public ?array $team,
        public ?string $createdBy,
        public ?string $authorName,
        public ?string $status,
        public ?int $version,
        public mixed $reviewStages,
        public ?string $reviewMode,
        public ?array $reviewers,
        public bool $reviewersLoaded,
        public ?array $documentReviewers,
        public ?array $documentReviewerUsers,
        public bool $documentReviewersLoaded,
        public ?string $createdAt,
        public ?string $updatedAt,
        public bool $hasReviewComments,
        public bool $hasUnreadReviewComments,
        public ?string $latestPublishedVersionId,
        public ?int $latestPublishedVersionNumber,
        public bool $canClone,
        public bool $canViewHistory,
        public bool $canCreateNewVersion,
        public ?string $workingVersionId,
        public ?string $latestPublishedName,
        public ?string $latestPublishedAt,
        public ?array $blocksAtPreviousSubmission,
        public ?array $reviewHistory,
        public ?string $themeId = null,
        /** @var array<string, mixed>|null Mini-payload del theme cuando la relación está cargada. */
        public ?array $themeMini = null,
        public ?string $documentReviewMode = null,
        public ?string $submissionChangelog = null,
        public bool $workingRevisionInProgress = false,
        public ?string $workingRevisionEditorName = null,
        public ?string $workingRevisionStartedAt = null,
    ) {}

    public static function fromModel(Template $m): self
    {
        $team = $m->getAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY);
        $authorName = $m->getAttribute('author_name')
            ?? ($m->relationLoaded('creator') ? $m->creator?->name : null);

        $blocksAtPreviousSubmission = null;
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
        $reviewersLoaded = $m->relationLoaded('reviewers');
        $reviewers = null;
        if ($reviewersLoaded) {
            $reviewers = $m->reviewers
                ->sortBy('stage')
                ->values()
                ->map(fn ($r) => [
                    'user_id' => (string) $r->user_id,
                    'user_name' => optional($r->user)->name,
                    'stage' => $r->stage,
                    'status' => $r->status,
                ])
                ->all();
        }

        $documentReviewersLoaded = $m->relationLoaded('documentReviewers');
        $documentReviewers = null;
        $documentReviewerUsers = null;
        if ($documentReviewersLoaded) {
            $documentReviewers = $m->documentReviewers
                ->map(fn ($v) => (string) $v->user_id)
                ->values()
                ->all();
            $documentReviewerUsers = $m->documentReviewers
                ->map(fn ($v) => [
                    'user_id' => (string) $v->user_id,
                    'user_name' => optional($v->user)->name,
                    'stage' => (int) $v->stage,
                ])
                ->values()
                ->all();
        }

        // Mini-payload del theme (sólo si la relación está cargada — eager-load explícito).
        $themeMini = null;
        if ($m->relationLoaded('theme') && $m->theme !== null) {
            $palette = (array) ($m->theme->palette ?? []);
            $typo = (array) ($m->theme->typography ?? []);
            $themeMini = [
                'id' => (string) $m->theme->id,
                'name' => (string) ($m->theme->name ?? ''),
                'palette' => [
                    'primary' => $palette['primary'] ?? null,
                    'secondary' => $palette['secondary'] ?? null,
                    'accent' => $palette['accent'] ?? null,
                    'background' => $palette['background'] ?? null,
                    'text' => $palette['text'] ?? null,
                ],
                'typography' => [
                    'heading_font' => $typo['heading_font'] ?? null,
                    'body_font' => $typo['body_font'] ?? null,
                ],
            ];
        }

        return new self(
            id: (string) $m->id,
            name: $m->name,
            description: $m->description,
            visibilityLevel: $m->visibility_level?->value,
            deliveryDeadline: $m->delivery_deadline?->toIso8601String(),
            studyTypeId: $m->study_type_id !== null ? (string) $m->study_type_id : null,
            studyId: $m->study_id !== null ? (string) $m->study_id : null,
            moduleId: $m->module_id !== null ? (string) $m->module_id : null,
            processId: $m->process_id !== null ? (string) $m->process_id : null,
            teamId: $m->team_id !== null ? (string) $m->team_id : null,
            team: is_array($team) ? $team : null,
            createdBy: $m->created_by !== null ? (string) $m->created_by : null,
            authorName: $authorName,
            status: $m->status !== null ? (string) $m->status : null,
            version: $m->version !== null ? (int) $m->version : null,
            reviewStages: $m->review_stages,
            reviewMode: $m->review_mode,
            reviewers: $reviewers,
            reviewersLoaded: $reviewersLoaded,
            documentReviewers: $documentReviewers,
            documentReviewerUsers: $documentReviewerUsers,
            documentReviewersLoaded: $documentReviewersLoaded,
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            hasReviewComments: (bool) ($m->getAttribute('has_review_comments') ?? false),
            hasUnreadReviewComments: (bool) ($m->getAttribute('has_unread_review_comments') ?? false),
            latestPublishedVersionId: $m->getAttribute('latest_published_version_id'),
            latestPublishedVersionNumber: $m->getAttribute('latest_published_version_number') !== null
                ? (int) $m->getAttribute('latest_published_version_number')
                : null,
            canClone: (bool) ($m->getAttribute('can_clone') ?? false),
            canViewHistory: (bool) ($m->getAttribute('can_view_history') ?? false),
            canCreateNewVersion: (bool) ($m->getAttribute('can_create_new_version') ?? false),
            workingVersionId: $m->head_entity_version_id !== null ? (string) $m->head_entity_version_id : null,
            latestPublishedName: $m->getAttribute('latest_published_name'),
            latestPublishedAt: IsoTimestamp::formatOptional($m->getAttribute('latest_published_at')),
            blocksAtPreviousSubmission: $blocksAtPreviousSubmission,
            reviewHistory: $reviewHistory,
            themeId: $m->theme_id !== null ? (string) $m->theme_id : null,
            themeMini: $themeMini,
            documentReviewMode: self::storedDocumentReviewModeFrom($m),
            submissionChangelog: self::submissionChangelogFrom($m),
            workingRevisionInProgress: (bool) ($m->getAttribute('working_revision_in_progress') ?? false),
            workingRevisionEditorName: $m->getAttribute('working_revision_editor_name'),
            workingRevisionStartedAt: IsoTimestamp::formatOptional($m->getAttribute('working_revision_started_at')),
        );
    }

    private static function submissionChangelogFrom(Template $m): ?string
    {
        $m->loadMissing('headVersion');

        return VersionSubmissionChangelog::forApiExposure(
            $m->status !== null ? (string) $m->status : null,
            $m->headVersion?->changelog,
        );
    }

    private static function storedDocumentReviewModeFrom(Template $m): ?string
    {
        $m->loadMissing('headVersion');
        $fields = data_get($m->headVersion?->snapshot_data, TemplateHeadSnapshot::JSON_TEMPLATE_KEY);

        return is_array($fields) ? TemplateHeadSnapshot::storedDocumentReviewMode($fields) : null;
    }

}
