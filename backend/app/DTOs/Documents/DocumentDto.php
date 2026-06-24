<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Models\Document;

final readonly class DocumentDto
{
    /**
     * @param  array<string, mixed>|null  $team
     * @param  array<int|string, mixed>|null  $reviewHistory
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

    /**
     * Construye el DTO desde el modelo, derivando los atributos calculados vía el
     * puente {@see DocumentDerivedAttributes::fromModel} (lee el modelo decorado).
     */
    public static function fromModel(Document $m): self
    {
        return self::fromDerived($m, DocumentDerivedAttributes::fromModel($m));
    }

    /**
     * DMS-B04: construcción con los atributos derivados EXPLÍCITOS. El DTO solo lee
     * columnas reales del modelo ($m->title, $m->status, …); todo lo derivado/
     * decorado llega en $d. Esto desacopla el DTO de las claves `getAttribute`.
     */
    public static function fromDerived(Document $m, DocumentDerivedAttributes $d): self
    {
        return new self(
            id: (string) $m->id,
            processId: $m->process_id !== null ? (string) $m->process_id : null,
            templateId: $m->template_id !== null ? (string) $m->template_id : null,
            templateVersionId: $m->template_version_id !== null ? (string) $m->template_version_id : null,
            templateVersionNumber: $d->templateVersionNumber,
            templateName: $d->templateName,
            team: $d->team,
            title: $m->title,
            studyTypeId: $m->study_type_id !== null ? (string) $m->study_type_id : null,
            studyId: $m->study_id !== null ? (string) $m->study_id : null,
            moduleId: $m->module_id !== null ? (string) $m->module_id : null,
            teamId: $m->team_id !== null ? (string) $m->team_id : null,
            deliveryDeadline: $m->delivery_deadline?->toIso8601String(),
            createdBy: $m->created_by !== null ? (string) $m->created_by : null,
            ownerId: $m->owner_id !== null ? (string) $m->owner_id : null,
            ownerName: $d->ownerName,
            visibilityLevel: $d->visibilityLevel,
            status: $m->status !== null ? (string) $m->status : null,
            currentVersion: $m->current_version !== null ? (int) $m->current_version : null,
            submittedAt: $m->submitted_at?->toIso8601String(),
            publishedAt: $m->published_at?->toIso8601String(),
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            isSharedWithMe: $d->isSharedWithMe,
            sharePermission: $d->sharePermission,
            canClone: $d->canClone,
            canViewHistory: $d->canViewHistory,
            canCreateNewVersion: $d->canCreateNewVersion,
            workingVersionId: $m->head_entity_version_id !== null ? (string) $m->head_entity_version_id : null,
            latestPublishedVersionId: $d->latestPublishedVersionId,
            latestPublishedVersionNumber: $d->latestPublishedVersionNumber,
            latestPublishedTitle: $d->latestPublishedTitle,
            reviewMode: $m->presentation()->reviewMode !== null ? (string) $m->presentation()->reviewMode : null,
            isAssignedReviewer: $d->isAssignedReviewer,
            reviewHistory: $d->reviewHistory,
            submissionChangelog: $d->submissionChangelog,
            workingRevisionInProgress: $d->workingRevisionInProgress,
            workingRevisionEditorName: $d->workingRevisionEditorName,
            workingRevisionStartedAt: $d->workingRevisionStartedAt,
        );
    }
}
