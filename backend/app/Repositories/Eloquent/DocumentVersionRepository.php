<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;

class DocumentVersionRepository implements DocumentVersionRepositoryInterface
{
    public function findOrFail(string $id): DocumentVersion
    {
        return DocumentVersion::query()->findOrFail($id);
    }

    public function findByDocumentAndVersionNumber(string $documentId, int $versionNumber): ?DocumentVersion
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->where('version_number', $versionNumber)
            ->first();
    }

    public function findOrFailByDocumentAndVersionNumber(string $documentId, int $versionNumber): DocumentVersion
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->where('version_number', $versionNumber)
            ->firstOrFail();
    }
}
