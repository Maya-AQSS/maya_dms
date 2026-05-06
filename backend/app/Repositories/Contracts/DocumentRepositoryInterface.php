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
     * Borrado lógico de documento.
     */
    public function delete(Document $document): void;

    /**
     * Actualiza metadatos editables del documento.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDocumentMetadata(Document $document, array $attributes): Document;

    /**
     * Actualiza owner del documento.
     */
    public function updateOwner(Document $document, string $newOwnerId): Document;

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
     * Elimina todas las revisiones del documento (uso en submitToReview para ciclo limpio).
     */
    public function deleteReviewsForDocument(string $documentId): void;

    /**
     * Elimina solo las revisiones pendientes, conservando las rechazadas como historial.
     */
    public function deletePendingReviewsForDocument(string $documentId): void;

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
    public function listOrderedByCreatedAtDesc(?string $processId = null): Collection;

    /**
     * Bandeja de validación de documentos pendiente para un revisor (documento en revisión y fila
     * `document_reviews` pending). En modo secuencial de la plantilla solo entran revisiones cuya
     * etapa coincide con la menor etapa aún pendiente del documento.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listPendingDocumentReviewInboxForUser(string $userId): Collection;

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
     * Última versión de snapshot del documento por número de versión.
     */
    public function findLatestDocumentVersionOrFail(string $documentId): DocumentVersion;

    /**
     * Contexto académico de módulo para creación documental.
     *
     * @return array{module_id: string, study_id: string, study_type_id: ?string}|null
     */
    public function findModuleContext(string $moduleId): ?array;

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

    /**
     * Mayor número de versión de bloque guardado (0 si no hay filas).
     */
    public function maxBlockVersionNumberForDocumentBlock(string $documentBlockId): int;

    /**
     * Inserta una fila append-only en block_versions.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>|null  $diff
     */
    public function insertDocumentBlockVersion(
        string $documentBlockId,
        string $documentId,
        int $versionNumber,
        array $content,
        ?array $diff,
        string $editedBy,
    ): void;

    /**
     * Ejecuta una operación dentro de transacción.
     */
    public function transaction(callable $callback): mixed;
}
