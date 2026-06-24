<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Versioning\WorkingRevisionConflictDto;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\JwtUser;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Support\WorkingRevisionConflictResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * DMS-B07 cluster C / B04: decoración de presentación de documentos —
 * adjunta metadatos derivados (versión publicada, número de versión de plantilla,
 * revisor asignado, conflicto de revisión en curso) y resuelve el contexto de
 * visibilidad del endpoint `show`. Extraído de DocumentService (Document-específico,
 * enfoque 3b). DocumentService delega aquí (firma pública intacta).
 *
 * Nota arquitectónica (B04): estos métodos siguen "decorando" el modelo Eloquent
 * vía setAttribute, que es el patrón embebido de la casa (también para Templates).
 * Centralizarlos aquí los aísla del god-service; retirar el setAttribute por completo
 * (construir DocumentDerivedAttributes explícito en el punto de serialización) es el
 * siguiente paso, ya habilitado por DocumentDto::fromDerived.
 */
class DocumentPresentationService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly UserDirectoryRepositoryInterface $userDirectoryRepository,
        private readonly CommentRepositoryInterface $commentRepository,
    ) {}

    public function attachLatestPublishedVersionMeta(Collection $documents): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $ids = $documents->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->values()->all();
        if ($ids === []) {
            return;
        }

        $latestByDocument = $this->entityVersionRepository->findLatestPublishedRowsByVersionables(
            Document::class,
            $ids,
        );

        foreach ($documents as $document) {
            $meta = $latestByDocument[(string) $document->id] ?? null;
            $document->setAttribute('latest_published_version_id', $meta['id'] ?? null);
            $document->setAttribute('latest_published_version_number', $meta['version_number'] ?? null);
            $document->setAttribute(
                'latest_published_title',
                $meta !== null ? $this->extractPublishedTitleFromSnapshot($meta['snapshot_data']) : null,
            );
        }
    }

    public function attachTemplateVersionNumbers(Collection $documents): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $versionIds = $documents
            ->pluck('template_version_id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
        if ($versionIds === []) {
            return;
        }

        $versionNumberById = $this->entityVersionRepository->findVersionNumbersByIds($versionIds);

        foreach ($documents as $document) {
            $templateVersionId = $document->template_version_id;
            if (! is_string($templateVersionId) || $templateVersionId === '') {
                continue;
            }
            if (array_key_exists($templateVersionId, $versionNumberById)) {
                $document->setAttribute('template_version_number', $versionNumberById[$templateVersionId]);
            }
        }
    }

    public function attachIsAssignedReviewerMeta(Collection $documents, string $viewerId): void
    {
        if ($documents->isEmpty()) {
            return;
        }

        $ids = $documents->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->values()->all();
        if ($ids === []) {
            return;
        }

        $assignedDocIds = array_flip(
            $this->documentRepository->findAssignedReviewerDocumentIds($ids, $viewerId),
        );

        foreach ($documents as $document) {
            $document->setAttribute('is_assigned_reviewer', array_key_exists((string) $document->id, $assignedDocIds));
        }
    }

    /**
     * Resuelve el contexto de visibilidad para el endpoint `show` de Document.
     *
     * Determina si el viewer debe recibir el snapshot publicado o el contenido vivo,
     * y si es revisor asignado activo. Encapsula la lógica de branching que antes
     * vivía directamente en DocumentController::show().
     *
     * @return array{serve_published_snapshot: bool, is_assigned_reviewer: bool}
     */
    public function resolveDocumentViewerContext(Document $resolved, string $documentId, string $viewerId): array
    {
        $servePublishedSnapshot = false;

        try {
            $this->documentRepository->findOrFail($documentId);
        } catch (ModelNotFoundException) {
            $servePublishedSnapshot = true;
        }

        // Admin de SOLO LECTURA: ve el contenido VIVO de cualquier documento (no se le
        // fuerza el snapshot publicado). No se le trata como titular/creador ni como revisor:
        // sigue sin poder mutar nada (las policies de escritura lo excluyen vía viewScoped()).
        $viewer = auth()->user();
        if ($viewer instanceof JwtUser && $viewer->canReadAll()) {
            $isAssignedReviewer = $resolved->status === 'in_review'
                && $this->documentRepository->isReviewerAssignedToDocument($documentId, $viewerId);

            return [
                'serve_published_snapshot' => $servePublishedSnapshot,
                'is_assigned_reviewer' => $isAssignedReviewer,
            ];
        }

        // Titular efectivo: si hay titular operativo (owner_id) solo cuenta ese; si no,
        // el autor (created_by) como fallback. Tras una cesión, el autor anterior deja de
        // tratarse como titular y recibe el snapshot publicado en lugar del contenido vivo.
        $ownerId = (string) $resolved->owner_id;
        $isCreator = $ownerId !== ''
            ? $viewerId === $ownerId
            : $viewerId === (string) $resolved->created_by;
        $isAssignedReviewer = false;

        if (! $servePublishedSnapshot && ! $isCreator && in_array($resolved->status, ['draft', 'in_review'], true)) {
            $isAssignedReviewer = $resolved->status === 'in_review'
                && $this->documentRepository->isReviewerAssignedToDocument($documentId, $viewerId);

            if (! $isAssignedReviewer) {
                $servePublishedSnapshot = true;
            }
        } elseif (! $servePublishedSnapshot && $resolved->status === 'in_review') {
            $isAssignedReviewer = $this->documentRepository->isReviewerAssignedToDocument($documentId, $viewerId);
        }

        return [
            'serve_published_snapshot' => $servePublishedSnapshot,
            'is_assigned_reviewer' => $isAssignedReviewer,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot  Ya decodificado por el cast
     *                                               `snapshot_data => array` de EntityVersion.
     */
    private function extractPublishedTitleFromSnapshot(?array $snapshot): ?string
    {
        if ($snapshot === null) {
            return null;
        }
        $title = data_get($snapshot, 'document.title');
        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        return $title;
    }

    /**
     * Obtiene el nombre del usuario propietario del documento.
     * Devuelve un nombre por defecto si el usuario no existe.
     */
    public function getOwnerNameForDocument(string $documentId): string
    {
        $document = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);

        if ($document->owner_id === null) {
            return 'otro usuario';
        }

        $ownerName = $this->userDirectoryRepository->findNameById($document->owner_id);

        return $ownerName ?? 'otro usuario';
    }

    public function resolveWorkingRevisionConflict(Document $document): WorkingRevisionConflictDto
    {
        $this->documentRepository->loadHeadVersion($document);
        $this->documentRepository->loadOwner($document);
        $editorName = $document->owner?->name;
        if (($editorName === null || $editorName === '') && $document->owner_id !== null) {
            $editorName = $this->userDirectoryRepository->findNameById($document->owner_id);
        }

        return WorkingRevisionConflictResolver::resolve(
            (string) $document->status,
            $this->findLatestPublishedVersion($document->id),
            $document->headVersion,
            is_string($editorName) && $editorName !== '' ? $editorName : null,
        );
    }

    public function attachWorkingRevisionPresentationMeta(Document $document): void
    {
        WorkingRevisionConflictResolver::attachToModel(
            $document,
            $this->resolveWorkingRevisionConflict($document),
        );
    }

    /**
     * Prepara un documento para visualización, adjuntando relaciones y metadatos derivados.
     * Centraliza la carga de relaciones y cálculo de metadatos que antes estaban dispersos
     * en el controller.
     */
    public function prepareDocumentForDisplay(
        Document $document,
        ?EntityVersion $latestPublished = null,
        bool $isAssignedReviewer = false,
    ): void {
        // Cargar propietario si no está cargado
        $this->documentRepository->loadOwner($document);

        // Si se proporciona última versión publicada, establecerla como relación
        if ($latestPublished !== null) {
            $document->setRelation('headVersion', $latestPublished);
        }

        // Determinar si hay comentarios visibles para el usuario sobre este documento.
        $hasReviewComments = $this->commentRepository->existsForCommentable(
            Document::class,
            (string) $document->getKey(),
        );
        $document->setAttribute('has_review_comments', $hasReviewComments);

        // Establecer si es revisor asignado
        $document->setAttribute('is_assigned_reviewer', $isAssignedReviewer);
    }

    private function findLatestPublishedVersion(string $documentId): ?EntityVersion
    {
        return $this->entityVersionRepository->findLatestPublishedForEntity(Document::class, $documentId);
    }
}
