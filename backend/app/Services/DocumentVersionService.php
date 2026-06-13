<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Versioning\DocumentVersionDetailDto;
use App\DTOs\Versioning\DocumentVersionDto;
use App\DTOs\Versioning\DocumentVersionSummaryDto;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class DocumentVersionService
{
    /** @var array<string, ?string> */
    private array $userNameCache = [];

    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly DocumentVersionBlockLayerResolver $documentVersionBlockLayerResolver,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
    ) {}

    /**
     * Localiza una versión snapshot del documento por id.
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersionDto
    {
        $this->documentRepository->findOrFail($documentId);

        $version = $this->documentRepository->findDocumentVersionInDocumentOrFail($documentId, $versionId);

        return new DocumentVersionDto(
            id: (string) $version->id,
            documentId: (string) $version->document_id,
            versionNumber: (int) $version->version_number,
        );
    }

    /**
     * Detalle de versión del documento aceptando id legacy o id polimórfico.
     *
     * Para filas en `document_versions`, `snapshot_data.blocks` se reconstruye con
     * {@see DocumentVersionBlockLayerResolver} (capas + fallback al JSON guardado).
     */
    public function findDocumentVersionDetailOrFail(string $documentId, string $versionId): DocumentVersionDetailDto
    {
        $this->documentRepository->findOrFail($documentId);

        try {
            $version = $this->documentRepository->findDocumentVersionInDocumentOrFail($documentId, $versionId);

            $resolved = $version->resolvedSnapshotData();
            $snapshotData = is_array($resolved) ? $resolved : [];
            $snapshotData['blocks'] = $this->documentVersionBlockLayerResolver->resolveBlocksSnapshot((string) $version->id);
            $documentMeta = isset($snapshotData['document']) && is_array($snapshotData['document'])
                ? $snapshotData['document']
                : [];
            $authorId = $this->resolveAuthorId($documentMeta['created_by'] ?? null);
            $ownerId = isset($documentMeta['owner_id']) && is_string($documentMeta['owner_id']) && $documentMeta['owner_id'] !== ''
                ? $documentMeta['owner_id']
                : null;
            $publishedBy = $this->resolvePublishedBy($version->triggered_by);

            return new DocumentVersionDetailDto(
                id: (string) $version->id,
                documentId: (string) $version->document_id,
                versionNumber: (int) $version->version_number,
                triggerEvent: (string) $version->trigger_event,
                triggeredBy: (string) $version->triggered_by,
                publishedByName: $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                authorName: $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                ownerName: $ownerId !== null ? $this->resolveUserNameById($ownerId) : null,
                reviewerNames: $this->extractReviewerNamesFromSnapshot($snapshotData),
                changelog: $version->notes,
                snapshotData: $snapshotData,
                createdAt: $version->created_at?->toIso8601String(),
            );
        } catch (ModelNotFoundException) {
            $entityVersion = $this->entityVersionRepository->findOrFail($versionId);

            if ((string) $entityVersion->versionable_type !== Document::class
                || (string) $entityVersion->versionable_id !== $documentId) {
                throw new ModelNotFoundException;
            }
            $snapshotData = is_array($entityVersion->snapshot_data) ? $entityVersion->snapshot_data : [];
            $documentMeta = isset($snapshotData['document']) && is_array($snapshotData['document'])
                ? $snapshotData['document']
                : [];
            $authorId = $this->resolveAuthorId($documentMeta['created_by'] ?? null, $entityVersion->created_by);
            $ownerId = isset($documentMeta['owner_id']) && is_string($documentMeta['owner_id']) && $documentMeta['owner_id'] !== ''
                ? $documentMeta['owner_id']
                : null;
            $publishedBy = $this->resolvePublishedBy($entityVersion->published_by, $entityVersion->created_by);

            return new DocumentVersionDetailDto(
                id: (string) $entityVersion->id,
                documentId: (string) $entityVersion->versionable_id,
                versionNumber: (int) $entityVersion->version_number,
                triggerEvent: 'published',
                triggeredBy: (string) ($entityVersion->published_by ?? $entityVersion->created_by),
                publishedByName: $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                authorName: $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                ownerName: $ownerId !== null ? $this->resolveUserNameById($ownerId) : null,
                reviewerNames: $this->extractReviewerNamesFromSnapshot($snapshotData),
                changelog: $entityVersion->changelog,
                snapshotData: $snapshotData,
                createdAt: ($entityVersion->published_at ?? $entityVersion->created_at)?->toIso8601String(),
            );
        }
    }

    /**
     * Metadatos de versiones del documento ordenados descendentemente.
     * No incluye el snapshot completo en el listado.
     *
     * @return list<DocumentVersionSummaryDto>
     */
    public function listDocumentVersions(string $documentId): array
    {
        $this->documentRepository->findOrFail($documentId);

        $entityVersions = $this->entityVersionRepository->listPublishedForEntityOrdered(
            Document::class,
            $documentId,
        )->map(function ($v): DocumentVersionSummaryDto {
            $snapshot = is_array($v->snapshot_data) ? $v->snapshot_data : [];
            $authorId = $this->resolveAuthorId(data_get($snapshot, 'document.created_by'), $v->created_by);
            $publishedBy = $this->resolvePublishedBy($v->published_by, $v->created_by);
            $reviewerNames = $this->extractReviewerNamesFromSnapshot($snapshot);

            return new DocumentVersionSummaryDto(
                id: (string) $v->id,
                documentId: (string) $v->versionable_id,
                versionNumber: (int) $v->version_number,
                triggerEvent: 'published',
                triggeredBy: (string) ($v->published_by ?? $v->created_by),
                publishedByName: $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                authorName: $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                reviewerNames: $reviewerNames,
                changelog: $v->changelog,
                notes: $v->changelog,
                createdAt: ($v->published_at ?? $v->created_at)?->toIso8601String(),
            );
        });

        $legacyVersions = $this->documentRepository->findLegacyDocumentVersionsOrderedDesc($documentId)
            ->map(function (DocumentVersion $v): DocumentVersionSummaryDto {
                $snapshot = $v->snapshot_data;
                if (is_string($snapshot)) {
                    $decoded = json_decode($snapshot, true);
                    $snapshot = is_array($decoded) ? $decoded : [];
                }
                $snapshot = is_array($snapshot) ? $snapshot : [];
                $authorId = $this->resolveAuthorId(data_get($snapshot, 'document.created_by'));
                $publishedBy = $this->resolvePublishedBy($v->triggered_by);

                $reviewerNamesLegacy = $this->extractReviewerNamesFromSnapshot($snapshot);

                return new DocumentVersionSummaryDto(
                    id: (string) $v->id,
                    documentId: (string) $v->document_id,
                    versionNumber: (int) $v->version_number,
                    triggerEvent: $v->trigger_event,
                    triggeredBy: $v->triggered_by,
                    publishedByName: $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                    authorName: $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                    reviewerNames: $reviewerNamesLegacy,
                    changelog: $v->notes,
                    notes: $v->notes,
                    createdAt: $v->created_at?->toIso8601String(),
                );
            });

        if ($entityVersions->isEmpty()) {
            return $legacyVersions->values()->all();
        }

        if ($legacyVersions->isEmpty()) {
            return $entityVersions->values()->all();
        }

        return collect($this->mergeDocumentVersionListRowsPreferringEntity($entityVersions, $legacyVersions))
            ->values()
            ->all();
    }

    /**
     * Lista combinada por número de versión; si existe fila en entity_versions y en document_versions, conserva entity.
     *
     * @param  Collection<int, DocumentVersionSummaryDto>  $entityRows
     * @param  Collection<int, DocumentVersionSummaryDto>  $legacyRows
     * @return list<DocumentVersionSummaryDto>
     */
    private function mergeDocumentVersionListRowsPreferringEntity(Collection $entityRows, Collection $legacyRows): array
    {
        /** @var array<int, DocumentVersionSummaryDto> $byNumber */
        $byNumber = [];
        foreach ($entityRows as $row) {
            $byNumber[$row->versionNumber] = $row;
        }
        foreach ($legacyRows as $row) {
            if (! array_key_exists($row->versionNumber, $byNumber)) {
                $byNumber[$row->versionNumber] = $row;
            }
        }

        return collect($byNumber)
            ->sortByDesc(static fn (DocumentVersionSummaryDto $row): int => $row->versionNumber)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    private function extractReviewerNamesFromSnapshot(array $snapshot): array
    {
        $reviewers = data_get($snapshot, 'reviewers');
        if (! is_array($reviewers)) {
            return [];
        }
        $names = [];
        foreach ($reviewers as $r) {
            $userId = $r['reviewer_id'] ?? null;
            if (! is_string($userId) || $userId === '') {
                continue;
            }
            $name = $this->resolveUserNameById($userId);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Identidad del autor de una versión: primer candidato que sea un string no
     * vacío (normalmente `snapshot.document.created_by`, con un fallback opcional
     * al `created_by` de la fila de versión).
     */
    private function resolveAuthorId(mixed $primary, mixed $fallback = null): ?string
    {
        return $this->firstNonEmptyString($primary, $fallback);
    }

    /**
     * Identidad de quien publicó la versión: primer candidato que sea un string
     * no vacío (p. ej. `published_by`/`triggered_by`, con fallback opcional a
     * `created_by`).
     */
    private function resolvePublishedBy(mixed $primary, mixed $fallback = null): ?string
    {
        return $this->firstNonEmptyString($primary, $fallback);
    }

    /**
     * Devuelve el primer argumento que sea un string no vacío, o null.
     */
    private function firstNonEmptyString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveUserNameById(string $userId): ?string
    {
        if (array_key_exists($userId, $this->userNameCache)) {
            return $this->userNameCache[$userId];
        }

        return $this->userNameCache[$userId] = $this->userDirectoryRepository->findNameById($userId);
    }
}
