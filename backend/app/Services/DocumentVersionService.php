<?php

declare(strict_types=1);

namespace App\Services;

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
     *
     * @return array{
     *   id: string,
     *   document_id: string,
     *   version_number: int,
     * }
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): array
    {
        $this->documentRepository->findOrFail($documentId);

        $version = $this->documentRepository->findDocumentVersionInDocumentOrFail($documentId, $versionId);

        return [
            'id' => $version->id,
            'document_id' => $version->document_id,
            'version_number' => (int) $version->version_number,
        ];
    }

    /**
     * Detalle de versión del documento aceptando id legacy o id polimórfico.
     *
     * Para filas en `document_versions`, `snapshot_data.blocks` se reconstruye con
     * {@see DocumentVersionBlockLayerResolver} (capas + fallback al JSON guardado).
     *
     * @return array{
     *   id: string,
     *   document_id: string,
     *   version_number: int,
     *   trigger_event: string,
     *   triggered_by: string,
     *   changelog: ?string,
     *   snapshot_data: array<string, mixed>,
     *   created_at: ?string
     * }
     */
    public function findDocumentVersionDetailOrFail(string $documentId, string $versionId): array
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
            $authorId = isset($documentMeta['created_by']) && is_string($documentMeta['created_by']) && $documentMeta['created_by'] !== ''
                ? $documentMeta['created_by']
                : null;
            $ownerId = isset($documentMeta['owner_id']) && is_string($documentMeta['owner_id']) && $documentMeta['owner_id'] !== ''
                ? $documentMeta['owner_id']
                : null;
            $publishedBy = is_string($version->triggered_by) && $version->triggered_by !== '' ? $version->triggered_by : null;

            return [
                'id' => $version->id,
                'document_id' => $version->document_id,
                'version_number' => (int) $version->version_number,
                'trigger_event' => (string) $version->trigger_event,
                'triggered_by' => (string) $version->triggered_by,
                'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                'owner_name' => $ownerId !== null ? $this->resolveUserNameById($ownerId) : null,
                'reviewer_names' => $this->extractReviewerNamesFromSnapshot($snapshotData),
                'changelog' => $version->notes,
                'snapshot_data' => $snapshotData,
                'created_at' => $version->created_at?->toIso8601String(),
            ];
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
            $authorId = isset($documentMeta['created_by']) && is_string($documentMeta['created_by']) && $documentMeta['created_by'] !== ''
                ? $documentMeta['created_by']
                : (is_string($entityVersion->created_by) && $entityVersion->created_by !== '' ? $entityVersion->created_by : null);
            $ownerId = isset($documentMeta['owner_id']) && is_string($documentMeta['owner_id']) && $documentMeta['owner_id'] !== ''
                ? $documentMeta['owner_id']
                : null;
            $publishedBy = is_string($entityVersion->published_by) && $entityVersion->published_by !== ''
                ? $entityVersion->published_by
                : (is_string($entityVersion->created_by) && $entityVersion->created_by !== '' ? $entityVersion->created_by : null);

            return [
                'id' => $entityVersion->id,
                'document_id' => (string) $entityVersion->versionable_id,
                'version_number' => (int) $entityVersion->version_number,
                'trigger_event' => 'published',
                'triggered_by' => (string) ($entityVersion->published_by ?? $entityVersion->created_by),
                'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                'owner_name' => $ownerId !== null ? $this->resolveUserNameById($ownerId) : null,
                'reviewer_names' => $this->extractReviewerNamesFromSnapshot($snapshotData),
                'changelog' => $entityVersion->changelog,
                'snapshot_data' => $snapshotData,
                'created_at' => ($entityVersion->published_at ?? $entityVersion->created_at)?->toIso8601String(),
            ];
        }
    }

    /**
     * Metadatos de versiones del documento ordenados descendentemente.
     * No incluye el snapshot completo en el listado.
     *
     * @return list<array{
     *   id: string,
     *   document_id: string,
     *   version_number: int,
     *   trigger_event: string,
     *   triggered_by: string,
     *   changelog: ?string,
     *   notes: ?string,
     *   created_at: ?string
     * }>
     */
    public function listDocumentVersions(string $documentId): array
    {
        $document = $this->documentRepository->findOrFail($documentId);

        $entityVersions = $this->entityVersionRepository->listPublishedForEntityOrdered(
            Document::class,
            $documentId,
        )->map(function ($v): array {
            $snapshot = is_array($v->snapshot_data) ? $v->snapshot_data : [];
            $authorId = data_get($snapshot, 'document.created_by');
            $authorId = is_string($authorId) && $authorId !== '' ? $authorId : (is_string($v->created_by) ? $v->created_by : null);
            $publishedBy = is_string($v->published_by) && $v->published_by !== '' ? $v->published_by : (is_string($v->created_by) ? $v->created_by : null);
            $reviewerNames = $this->extractReviewerNamesFromSnapshot($snapshot);

            return [
                'id' => $v->id,
                'document_id' => $v->versionable_id,
                'version_number' => (int) $v->version_number,
                'trigger_event' => 'published',
                'triggered_by' => (string) ($v->published_by ?? $v->created_by),
                'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                'reviewer_names' => $reviewerNames,
                'changelog' => $v->changelog,
                'notes' => $v->changelog,
                'created_at' => ($v->published_at ?? $v->created_at)?->toIso8601String(),
            ];
        });

        $legacyVersions = $this->documentRepository->findLegacyDocumentVersionsOrderedDesc($documentId)
            ->map(function (DocumentVersion $v): array {
                $snapshot = $v->snapshot_data;
                if (is_string($snapshot)) {
                    $decoded = json_decode($snapshot, true);
                    $snapshot = is_array($decoded) ? $decoded : [];
                }
                $snapshot = is_array($snapshot) ? $snapshot : [];
                $authorId = data_get($snapshot, 'document.created_by');
                $authorId = is_string($authorId) && $authorId !== '' ? $authorId : null;
                $publishedBy = is_string($v->triggered_by) && $v->triggered_by !== '' ? $v->triggered_by : null;

                $reviewerNamesLegacy = $this->extractReviewerNamesFromSnapshot($snapshot);

                return [
                    'id' => $v->id,
                    'document_id' => $v->document_id,
                    'version_number' => $v->version_number,
                    'trigger_event' => $v->trigger_event,
                    'triggered_by' => $v->triggered_by,
                    'published_by_name' => $publishedBy !== null ? $this->resolveUserNameById($publishedBy) : null,
                    'author_name' => $authorId !== null ? $this->resolveUserNameById($authorId) : null,
                    'reviewer_names' => $reviewerNamesLegacy,
                    'changelog' => $v->notes,
                    'notes' => $v->notes,
                    'created_at' => $v->created_at?->toIso8601String(),
                ];
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
     * @param  Collection<int, array<string, mixed>>  $entityRows
     * @param  Collection<int, array<string, mixed>>  $legacyRows
     * @return list<array<string, mixed>>
     */
    private function mergeDocumentVersionListRowsPreferringEntity(Collection $entityRows, Collection $legacyRows): array
    {
        /** @var array<int, array<string, mixed>> $byNumber */
        $byNumber = [];
        foreach ($entityRows as $row) {
            $byNumber[(int) $row['version_number']] = $row;
        }
        foreach ($legacyRows as $row) {
            $n = (int) $row['version_number'];
            if (! array_key_exists($n, $byNumber)) {
                $byNumber[$n] = $row;
            }
        }

        return collect($byNumber)
            ->sortByDesc(static fn (array $row): int => (int) $row['version_number'])
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

    private function resolveUserNameById(string $userId): ?string
    {
        if (array_key_exists($userId, $this->userNameCache)) {
            return $this->userNameCache[$userId];
        }

        return $this->userNameCache[$userId] = $this->userDirectoryRepository->findNameById($userId);
    }
}
