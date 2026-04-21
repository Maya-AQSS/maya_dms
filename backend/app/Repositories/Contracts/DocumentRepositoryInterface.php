<?php

namespace App\Repositories\Contracts;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Models\DocumentVersion;
use Illuminate\Support\Collection;

interface DocumentRepositoryInterface
{
    /**
     * Localiza un documento por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Document;

    /**
     * Crea el documento y sus bloques iniciales en una transacción.
     *
     * @param  array<string, mixed>  $documentAttributes
     * @param  list<array{template_block_id: string, content: mixed, sort_order: int}>  $blockRows
     */
    public function createDocumentWithBlocks(array $documentAttributes, array $blockRows): Document;

    /**
     * Localiza un bloque por su ID dentro del documento o lanza ModelNotFoundException.
     */
    public function findBlockInDocumentOrFail(string $documentId, string $blockId): DocumentBlock;

    /**
     * Persiste un bloque del documento.
     */
    public function saveBlock(DocumentBlock $block): void;

    /**
     * Indica si el usuario es autor (owner_id / created_by) o revisor asignado
     * del documento. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrReviewer(string $documentId, string $userId): bool;

    /**
     * Revisiones del documento ordenadas por etapa.
     *
     * @return Collection<int, DocumentReview>
     */
    public function listReviewsForDocument(string $documentId): Collection;

    /**
     * Busca una revisión por ID si pertenece al documento indicado.
     */
    public function findReviewInDocument(string $reviewId, string $documentId): ?DocumentReview;

    /**
     * Elimina las revisiones del documento.
     */
    public function deleteReviewsForDocument(string $documentId): void;

    /**
     * Crea las revisiones pendientes del documento.
     * 
     * @param  list<array{reviewer_id: string, stage: int}>  $rows
     */
    public function createPendingReviews(string $documentId, array $rows): void;

    /**
     * Cuenta las revisiones pendientes del documento.
     */
    public function countPendingReviewsForDocument(string $documentId): int;

    /**
     * Menor número de etapa entre revisiones pendientes, o null si no hay ninguna.
     */
    public function minPendingReviewStageForDocument(string $documentId): ?int;

    /**
     * Guarda una revisión del documento.
     */
    public function saveReview(DocumentReview $review): void;

    /**
     * Lista documentos visibles para el usuario actual ordenados por fecha de creación descendente.
     *
     * @return Collection<int, Document>
     */
    public function listOrderedByCreatedAtDesc(): Collection;

    /**
     * Mayor número de versión de snapshot guardado para el documento (0 si no hay ninguna).
     */
    public function maxDocumentVersionNumber(string $documentId): int;

    /**
     * Inserta un registro append-only en document_versions.
     *
     * @param  array<string, mixed>  $snapshotData
     */
    public function insertDocumentVersion(
        string $documentId,
        int $versionNumber,
        string $triggerEvent,
        string $triggeredBy,
        array $snapshotData,
        ?string $notes = null,
    ): void;

    /**
     * Localiza una fila de document_versions por id dentro del documento.
     */
    public function findDocumentVersionInDocumentOrFail(string $documentId, string $versionId): DocumentVersion;

    /**
     * Crea o actualiza un compartido (document_id, user_id) único.
     */
    public function upsertDocumentShare(
        string $documentId,
        string $userId,
        string $permission,
        string $grantedBy,
    ): void;

    /**
     * Elimina un compartido; no lanza si no existía.
     */
    public function deleteDocumentShare(string $documentId, string $userId): void;

    /**
     * Permisos de compartición del usuario sobre los documentos indicados.
     *
     * @param  list<string>  $documentIds
     * @return array<string, string> mapa document_id => permission (read|edit)
     */
    public function sharePermissionsForViewer(array $documentIds, string $userId): array;
}
