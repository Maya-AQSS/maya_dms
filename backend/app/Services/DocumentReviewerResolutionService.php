<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\ReviewerCandidateDto;
use App\DTOs\Documents\ReviewerPoolDto;
use App\Models\Document;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Support\DocumentReviewModeResolver;

/**
 * DMS-B07 (cluster A): resolución de candidatos/validadores de revisión de un
 * documento. Extraído de DocumentService para reducir el god-service; mismo
 * comportamiento, mismas fuentes (versión de plantilla anclada → config viva).
 */
class DocumentReviewerResolutionService
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly DocumentReviewModeResolver $documentReviewModeResolver,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
    ) {}

    /**
     * Resuelve candidatos de revisión desde la versión de plantilla anclada al documento.
     *
     * @return list<array{reviewer_id: string, stage: int}>
     */
    public function resolveReviewCandidatesFromTemplateVersion(Document $document): array
    {
        $versionId = $document->template_version_id;
        if (! is_string($versionId) || $versionId === '') {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $entityVersion = $this->entityVersionRepository->findPublishedByIdForVersionable(
            $versionId,
            Template::class,
            (string) $document->template_id,
        );

        if ($entityVersion === null || ! is_array($entityVersion->snapshot_data)) {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $reviewersPayload = $entityVersion->snapshot_data['reviewers'] ?? null;
        if (! is_array($reviewersPayload)) {
            return $this->resolveReviewCandidatesFromTemplateLiveConfig($document);
        }

        $documentReviewers = $reviewersPayload['document_reviewers'] ?? [];
        if (! is_array($documentReviewers) || $documentReviewers === []) {
            return [];
        }

        $candidates = [];
        $fallbackStage = 1;
        foreach ($documentReviewers as $row) {
            if (! is_array($row) || ! isset($row['user_id']) || ! is_string($row['user_id']) || $row['user_id'] === '') {
                continue;
            }
            $resolvedStage = $this->resolveDocumentReviewerStage($row['stage'] ?? null, $fallbackStage);
            $candidates[] = [
                'reviewer_id' => $row['user_id'],
                'stage' => $resolvedStage,
            ];
            $fallbackStage++;
        }

        return $candidates;
    }

    /**
     * @return list<array{reviewer_id: string, stage: int}>
     */
    private function resolveReviewCandidatesFromTemplateLiveConfig(Document $document): array
    {
        $template = $this->templateRepository
            ->findForDocumentReviewCandidatesWithoutCatalogScope((string) $document->template_id);

        if ($template === null || $template->documentReviewers->isEmpty()) {
            return [];
        }

        $candidates = [];
        $fallbackStage = 1;
        foreach ($template->documentReviewers as $dr) {
            $candidates[] = [
                'reviewer_id' => (string) $dr->user_id,
                'stage' => $this->resolveDocumentReviewerStage($dr->stage, $fallbackStage),
            ];
            $fallbackStage++;
        }

        return $candidates;
    }

    /**
     * Etapa desde fila persistida; fallback para snapshots legacy sin `stage`.
     */
    private function resolveDocumentReviewerStage(mixed $stageValue, int $fallbackStage): int
    {
        return is_numeric($stageValue) && (int) $stageValue > 0
            ? (int) $stageValue
            : $fallbackStage;
    }

    /**
     * Pool de validadores efectivo del documento para mostrar en el wizard.
     *
     * Los `document_reviewers` se resuelven con la MISMA fuente que el envío a
     * revisión ({@see self::resolveReviewCandidatesFromTemplateVersion}): la versión
     * publicada de plantilla anclada al documento (con fallback a la config viva).
     * Así la UI muestra exactamente los validadores que se materializarán al validar,
     * sin requerir acceso de lectura a la plantilla.
     *
     * Si la plantilla no define validadores de documento pero sí revisores de plantilla,
     * se devuelven estos últimos como información (`template_fallback`): no participan en
     * la validación del documento, que se publicará directamente.
     */
    public function getDocumentReviewerPool(Document $document): ReviewerPoolDto
    {
        $reviewMode = $this->documentReviewModeResolver->resolve($document);

        $candidates = $this->resolveReviewCandidatesFromTemplateVersion($document);
        if ($candidates !== []) {
            return new ReviewerPoolDto(
                kind: 'document',
                reviewMode: $reviewMode,
                reviewers: array_map(fn (array $c) => new ReviewerCandidateDto(
                    id: $c['reviewer_id'],
                    name: $this->userDirectoryRepository->findNameById($c['reviewer_id']),
                    stage: $c['stage'],
                ), $candidates),
            );
        }

        $templateReviewerIds = $this->resolveTemplateReviewerIdsFromAnchoredVersion($document);
        if ($templateReviewerIds !== []) {
            return new ReviewerPoolDto(
                kind: 'template_fallback',
                reviewMode: $reviewMode,
                reviewers: array_map(fn (string $id) => new ReviewerCandidateDto(
                    id: $id,
                    name: $this->userDirectoryRepository->findNameById($id),
                    stage: null,
                ), $templateReviewerIds),
            );
        }

        return new ReviewerPoolDto(
            kind: 'none',
            reviewMode: $reviewMode,
            reviewers: [],
        );
    }

    /**
     * IDs de revisores de plantilla (`template_reviewers`) del snapshot de la versión
     * publicada anclada al documento. Solo informativos para la UI.
     *
     * @return list<string>
     */
    private function resolveTemplateReviewerIdsFromAnchoredVersion(Document $document): array
    {
        $versionId = $document->template_version_id;
        if (! is_string($versionId) || $versionId === '') {
            return [];
        }

        $entityVersion = $this->entityVersionRepository->findPublishedByIdForVersionable(
            $versionId,
            Template::class,
            (string) $document->template_id,
        );

        if ($entityVersion === null || ! is_array($entityVersion->snapshot_data)) {
            return [];
        }

        $rows = data_get($entityVersion->snapshot_data, 'reviewers.template_reviewers');
        if (! is_array($rows)) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $userId = is_array($row) ? ($row['user_id'] ?? null) : null;
            if (is_string($userId) && $userId !== '') {
                $ids[] = $userId;
            }
        }

        return $ids;
    }
}
