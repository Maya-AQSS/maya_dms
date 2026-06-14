<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\DocumentShareResultDto;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de compartidos de documento.
 *
 * El control de acceso (solo el titular gestiona compartidos) vive en
 * {@see DocumentPolicy::share} y se aplica en el controlador con
 * `$this->authorize('share', $document)`. Este Service asume que el documento
 * ya viene autorizado y solo aplica las reglas de negocio (no auto-compartir,
 * el titular ya tiene acceso).
 */
class DocumentShareService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function upsertDocumentShare(
        string $documentId,
        string $targetUserId,
        string $permission,
        string $actorId,
    ): DocumentShareResultDto {
        $document = $this->documentRepository->findOrFail($documentId);

        if ($targetUserId === $actorId) {
            throw ValidationException::withMessages([
                'user_id' => [__('validation.share.self')],
            ]);
        }

        if ($targetUserId === $document->owner_id) {
            throw ValidationException::withMessages([
                'user_id' => [__('validation.share.owner_has_access')],
            ]);
        }

        $this->documentRepository->upsertDocumentShare(
            $documentId,
            $targetUserId,
            $permission,
            $actorId,
        );

        return new DocumentShareResultDto(
            userId: $targetUserId,
            permission: $permission,
            grantedBy: $actorId,
        );
    }

    public function removeDocumentShare(string $documentId, string $targetUserId, string $actorId): void
    {
        // Acceso ya autorizado por DocumentPolicy::share en el controlador.
        $this->documentRepository->deleteDocumentShare($documentId, $targetUserId);
    }

    /**
     * @param  Collection<int, Document>  $documents
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
