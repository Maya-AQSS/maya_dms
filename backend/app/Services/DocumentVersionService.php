<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;

class DocumentVersionService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
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
        );

        if ($entityVersions->isNotEmpty()) {
            return $entityVersions
                ->sortByDesc('version_number')
                ->values()
                ->map(static function ($v): array {
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
                })
                ->all();
        }

        return $document->versions()
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
            })
            ->values()
            ->all();
    }
}
