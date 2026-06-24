<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Models\Document;
use App\Services\DocumentTemplateVersionNumberResolver;
use App\Support\IsoTimestamp;
use App\Support\VersionSubmissionChangelog;

/**
 * DMS-B04: atributos DERIVADOS de un documento (no columnas de BD) que distintos
 * colaboradores calculan a lo largo de la request: permisos de presentación
 * (can_clone…), metadatos de versión publicada, estado de revisión, etc.
 *
 * Antes el `DocumentDto::fromModel` los leía directamente del modelo vía
 * `getAttribute('clave')` — acoplando el DTO a las claves que cada colaborador
 * "decoraba" sobre el Eloquent model. Este value object aísla esa resolución en
 * un único sitio: `fromModel()` es el puente que lee el modelo decorado, y
 * `DocumentDto::fromModel` consume el struct (ya no toca `getAttribute`).
 *
 * El camino limpio (sin decorar el modelo) es construir este struct
 * explícitamente y pasarlo a {@see DocumentDto::fromDerived}.
 */
final readonly class DocumentDerivedAttributes
{
    /**
     * @param  array<string, mixed>|null  $team
     * @param  array<int|string, mixed>|null  $reviewHistory
     */
    public function __construct(
        public ?array $team = null,
        public ?string $ownerName = null,
        public ?string $templateName = null,
        public ?int $templateVersionNumber = null,
        public ?string $visibilityLevel = null,
        public bool $isSharedWithMe = false,
        public ?string $sharePermission = null,
        public bool $canClone = false,
        public bool $canViewHistory = false,
        public bool $canCreateNewVersion = false,
        public ?string $latestPublishedVersionId = null,
        public ?int $latestPublishedVersionNumber = null,
        public ?string $latestPublishedTitle = null,
        public bool $isAssignedReviewer = false,
        public ?array $reviewHistory = null,
        public ?string $submissionChangelog = null,
        public bool $workingRevisionInProgress = false,
        public ?string $workingRevisionEditorName = null,
        public ?string $workingRevisionStartedAt = null,
    ) {}

    /**
     * Puente: deriva los atributos del modelo (decorado por los colaboradores).
     */
    public static function fromModel(Document $m): self
    {
        $team = $m->presentation()->team;

        $latestPublishedVersionNumber = $m->presentation()->latestPublishedVersionNumber;

        return new self(
            team: is_array($team) ? $team : null,
            ownerName: $m->getAttribute('owner_name')
                ?? ($m->relationLoaded('owner') ? $m->owner?->name : null),
            templateName: self::resolveTemplateName($m),
            templateVersionNumber: self::resolveTemplateVersionNumber($m),
            visibilityLevel: $m->relationLoaded('template') && $m->template !== null
                ? $m->template->visibility_level->value
                : null,
            isSharedWithMe: (bool) ($m->presentation()->isSharedWithMe ?? false),
            sharePermission: $m->presentation()->sharePermission,
            canClone: (bool) ($m->presentation()->canClone ?? false),
            canViewHistory: (bool) ($m->presentation()->canViewHistory ?? false),
            canCreateNewVersion: (bool) ($m->presentation()->canCreateNewVersion ?? false),
            latestPublishedVersionId: $m->presentation()->latestPublishedVersionId,
            latestPublishedVersionNumber: $latestPublishedVersionNumber !== null
                ? (int) $latestPublishedVersionNumber
                : null,
            latestPublishedTitle: $m->presentation()->latestPublishedTitle,
            isAssignedReviewer: (bool) ($m->presentation()->isAssignedReviewer ?? false),
            reviewHistory: self::resolveReviewHistory($m),
            submissionChangelog: self::submissionChangelogFrom($m),
            workingRevisionInProgress: (bool) ($m->presentation()->workingRevisionInProgress ?? false),
            workingRevisionEditorName: $m->presentation()->workingRevisionEditorName,
            workingRevisionStartedAt: IsoTimestamp::formatOptional($m->presentation()->workingRevisionStartedAt),
        );
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private static function resolveReviewHistory(Document $m): ?array
    {
        if (! $m->relationLoaded('headVersion') || $m->headVersion === null) {
            return null;
        }

        $changeSet = $m->headVersion->change_set;
        if (is_array($changeSet) && count($changeSet) > 0) {
            return $changeSet;
        }

        return null;
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
     * {@see DocumentTemplateVersionNumberResolver}, que resuelve a través de la
     * capa Repository (sin acceso a Eloquent dentro del DTO).
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

    /**
     * Número de versión de la plantilla con la que se creó el documento.
     *
     * Prioridad: portador de presentación `presentation()->templateVersionNumber`
     * (que `DocumentPresentationService::attachTemplateVersionNumbers` adjunta en
     * lote) → relación `templateVersion` ya cargada en todos los caminos de lectura
     * (sin N+1). El mapper NO accede a la BD: si ninguno está disponible devuelve
     * null; la resolución contra el resolver es responsabilidad del Service antes
     * de construir el DTO.
     */
    private static function resolveTemplateVersionNumber(Document $m): ?int
    {
        $preloaded = $m->presentation()->templateVersionNumber;
        if (is_numeric($preloaded)) {
            return (int) $preloaded;
        }

        if ($m->template_version_id === null) {
            return null;
        }

        if ($m->relationLoaded('templateVersion') && $m->templateVersion !== null) {
            return (int) $m->templateVersion->version_number;
        }

        return null;
    }
}
