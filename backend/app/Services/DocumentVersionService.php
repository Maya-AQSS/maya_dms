<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;

class DocumentVersionService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersion
    {
        $this->documentRepository->findOrFail($documentId);

        return $this->documentRepository->findDocumentVersionInDocumentOrFail($documentId, $versionId);
    }

    /**
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
