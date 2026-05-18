<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Models\Document;
use App\Services\DocumentTemplateVersionNumberResolver;
use App\Support\ApiEmbeddedTeamResponse;

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
        public ?string $workingVersionId,
        public ?string $latestPublishedVersionId,
        public ?int $latestPublishedVersionNumber,
        public ?string $latestPublishedTitle,
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

        return new self(
            id: (string) $m->id,
            processId: $m->process_id !== null ? (string) $m->process_id : null,
            templateId: $m->template_id !== null ? (string) $m->template_id : null,
            templateVersionId: $m->template_version_id !== null ? (string) $m->template_version_id : null,
            templateVersionNumber: $templateVersionNumber,
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
            workingVersionId: $m->head_entity_version_id !== null ? (string) $m->head_entity_version_id : null,
            latestPublishedVersionId: $m->getAttribute('latest_published_version_id'),
            latestPublishedVersionNumber: $m->getAttribute('latest_published_version_number') !== null
                ? (int) $m->getAttribute('latest_published_version_number')
                : null,
            latestPublishedTitle: $m->getAttribute('latest_published_title'),
        );
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
