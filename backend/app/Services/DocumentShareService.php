<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Repositories\Contracts\DocumentRepositoryInterface;

class DocumentShareService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    /**
     * @return array{user_id: string, permission: string, granted_by: string}
     */
    public function upsertDocumentShare(
        string $documentId,
        string $targetUserId,
        string $permission,
        string $actorId,
    ): array {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->owner_id !== $actorId) {
            abort(403, 'Solo el titular puede gestionar colaboradores.');
        }

        if ($targetUserId === $actorId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' => ['No puedes compartir el documento contigo mismo.'],
            ]);
        }

        if ($targetUserId === $document->owner_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' => ['El titular ya tiene acceso completo al documento.'],
            ]);
        }

        $this->documentRepository->upsertDocumentShare(
            $documentId,
            $targetUserId,
            $permission,
            $actorId,
        );

        return [
            'user_id' => $targetUserId,
            'permission' => $permission,
            'granted_by' => $actorId,
        ];
    }

    public function removeDocumentShare(string $documentId, string $targetUserId, string $actorId): void
    {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($document->owner_id !== $actorId) {
            abort(403, 'Solo el titular puede gestionar colaboradores.');
        }

        $this->documentRepository->deleteDocumentShare($documentId, $targetUserId);
    }

    /**
     * @param  Collection<int, \App\Models\Document>  $documents
     */
    public function attachShareMetadataForViewer(Collection $documents, string $viewerId): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $ids = $documents->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $byDoc = $this->documentRepository->sharePermissionsForViewer($ids, $viewerId);

        foreach ($documents as $document) {
            $permission = $byDoc[(string) $document->getKey()] ?? null;
            $document->setAttribute('viewer_share_permission', $permission);
            $document->setAttribute('is_shared_with_me', $permission !== null);
        }
    }
}
