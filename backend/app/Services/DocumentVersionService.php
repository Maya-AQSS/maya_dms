<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class DocumentVersionService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly DocumentVersionBlockLayerResolver $documentVersionBlockLayerResolver,
    ) {}

    /**
     * Localiza una versión snapshot del documento por id.
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersion
    {
        $document = $this->documentRepository->findOrFail($documentId);

        return $this->documentRepository->findDocumentVersionInDocumentOrFail($documentId, $versionId);
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

            return [
                'id' => $version->id,
                'document_id' => $version->document_id,
                'version_number' => (int) $version->version_number,
                'trigger_event' => (string) $version->trigger_event,
                'triggered_by' => (string) $version->triggered_by,
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

            return [
                'id' => $entityVersion->id,
                'document_id' => (string) $entityVersion->versionable_id,
                'version_number' => (int) $entityVersion->version_number,
                'trigger_event' => 'published',
                'triggered_by' => (string) ($entityVersion->published_by ?? $entityVersion->created_by),
                'changelog' => $entityVersion->changelog,
                'snapshot_data' => is_array($entityVersion->snapshot_data) ? $entityVersion->snapshot_data : [],
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
        )->map(static function ($v): array {
            return [
                'id' => $v->id,
                'document_id' => $v->versionable_id,
                'version_number' => (int) $v->version_number,
                'trigger_event' => 'published',
                'triggered_by' => (string) ($v->published_by ?? $v->created_by),
                'changelog' => $v->changelog,
                'notes' => $v->changelog,
                'created_at' => ($v->published_at ?? $v->created_at)?->toIso8601String(),
            ];
        });

        $legacyVersions = $document->versions()
            ->orderByDesc('version_number')
            ->get()
            ->map(static function (DocumentVersion $v): array {
                return [
                    'id' => $v->id,
                    'document_id' => $v->document_id,
                    'version_number' => $v->version_number,
                    'trigger_event' => $v->trigger_event,
                    'triggered_by' => $v->triggered_by,
                    'changelog' => $v->notes,
                    'notes' => $v->notes,
                    'created_at' => $v->created_at?->toIso8601String(),
                ];
            });

        if ($entityVersions->isEmpty()) {
            return $legacyVersions
                ->values()
                ->all();
        }

        if ($legacyVersions->isEmpty()) {
            return $entityVersions
                ->values()
                ->all();
        }

        return $this->mergeDocumentVersionListRowsPreferringEntity($entityVersions, $legacyVersions);
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
}
