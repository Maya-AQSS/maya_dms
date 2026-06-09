<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Documents\DocumentVersionSnapshotDto;
use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;

class DocumentVersionRepository implements DocumentVersionRepositoryInterface
{
    public function findOrFail(string $id): DocumentVersion
    {
        return DocumentVersion::query()->findOrFail($id);
    }

    /**
     * Fetch version snapshot data without exposing Eloquent model.
     */
    public function findOrFailAsSnapshot(string $id): DocumentVersionSnapshotDto
    {
        $version = $this->findOrFail($id);

        return new DocumentVersionSnapshotDto(
            id: (string) $version->id,
            documentId: (string) $version->document_id,
            versionNumber: (int) $version->version_number,
            snapshotData: $this->resolveSnapshotData($version),
        );
    }

    public function findByDocumentAndVersionNumber(string $documentId, int $versionNumber): ?DocumentVersion
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->where('version_number', $versionNumber)
            ->first();
    }

    public function findByDocumentAndVersionNumberAsSnapshot(string $documentId, int $versionNumber): ?DocumentVersionSnapshotDto
    {
        $version = $this->findByDocumentAndVersionNumber($documentId, $versionNumber);
        if ($version === null) {
            return null;
        }

        return new DocumentVersionSnapshotDto(
            id: (string) $version->id,
            documentId: (string) $version->document_id,
            versionNumber: (int) $version->version_number,
            snapshotData: $this->resolveSnapshotData($version),
        );
    }

    public function findOrFailByDocumentAndVersionNumber(string $documentId, int $versionNumber): DocumentVersion
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->where('version_number', $versionNumber)
            ->firstOrFail();
    }

    public function findOrFailByDocumentAndVersionNumberAsSnapshot(string $documentId, int $versionNumber): DocumentVersionSnapshotDto
    {
        $version = $this->findOrFailByDocumentAndVersionNumber($documentId, $versionNumber);

        return new DocumentVersionSnapshotDto(
            id: (string) $version->id,
            documentId: (string) $version->document_id,
            versionNumber: (int) $version->version_number,
            snapshotData: $this->resolveSnapshotData($version),
        );
    }

    /**
     * Resolve snapshot data from model.
     * Encapsulates model method access in repository layer.
     *
     * @return array<string, mixed>
     */
    private function resolveSnapshotData(DocumentVersion $version): array
    {
        $snap = $version->resolvedSnapshotData();

        return is_array($snap) ? $snap : [];
    }
}
