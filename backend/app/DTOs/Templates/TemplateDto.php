<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

use App\Models\Template;
use App\Support\ApiEmbeddedTeamResponse;
use Illuminate\Support\Carbon;

final readonly class TemplateDto
{
    /**
     * @param  array<string, mixed>|null  $team
     * @param  list<array{user_id: string, user_name: ?string, stage: int|null, status: string|null}>|null  $reviewers
     * @param  list<string>|null  $documentReviewers
     * @param  list<array{user_id: string, user_name: ?string}>|null  $documentReviewerUsers
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
        public ?string $latestPublishedVersionId,
        public ?int $latestPublishedVersionNumber,
        public bool $canClone,
        public ?string $workingVersionId,
        public ?string $latestPublishedName,
        public ?string $latestPublishedAt,
    ) {}

    public static function fromModel(Template $m): self
    {
        $team = $m->getAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY);
        $authorName = $m->getAttribute('author_name')
            ?? ($m->relationLoaded('creator') ? $m->creator?->name : null);
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
                ])
                ->values()
                ->all();
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
            latestPublishedVersionId: $m->getAttribute('latest_published_version_id'),
            latestPublishedVersionNumber: $m->getAttribute('latest_published_version_number') !== null
                ? (int) $m->getAttribute('latest_published_version_number')
                : null,
            canClone: (bool) ($m->getAttribute('can_clone') ?? false),
            workingVersionId: $m->head_entity_version_id !== null ? (string) $m->head_entity_version_id : null,
            latestPublishedName: $m->getAttribute('latest_published_name'),
            latestPublishedAt: self::formatOptionalIso($m->getAttribute('latest_published_at')),
        );
    }

    private static function formatOptionalIso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
