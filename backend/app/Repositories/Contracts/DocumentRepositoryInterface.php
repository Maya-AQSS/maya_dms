<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Documents\DocumentFilterDto;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Models\DocumentVersion;
use App\Support\DocumentHeadSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DocumentRepositoryInterface
{
    /**
     * Localiza un documento por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Document;

    /**
     * Localiza un documento con bloques y theme cargados para renderizado HTML/PDF.
     * Lanza NotFoundHttpException si no existe.
     */
    public function findWithBlocksAndThemeOrFail(string $id): Document;

    /**
     * Recarga el documento sin el scope `user_access` (cabezal unido).
     *
     * Tras mutar el cabezal, el actor puede dejar de cumplir visibilidad (p. ej. revisor tras rechazo → borrador).
     * Solo usar cuando el id ya pasó autorización en la misma operación.
     */
    public function findOrFailForRefreshAfterMutation(string $id): Document;

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
     * Fusiona atributos delegados en la versión cabezal y sincroniza {@see EntityVersion::status} si viene `status`.
     *
     * @param  array<string, mixed>  $updates  Claves de {@see DocumentHeadSnapshot::DELEGATED_ATTRIBUTES}.
     */
    public function mergeHeadWorkingCopy(Document $document, array $updates): Document;

    /**
     * Crea el documento y sus bloques iniciales en una transacción.
     *
     * @param  array<string, mixed>  $documentAttributes
     * @param  list<array{template_block_id: string, content: mixed, sort_order: int, is_filled?: bool, last_edited_by?: ?string}>  $blockRows
     */
    public function createDocumentWithBlocks(array $documentAttributes, array $blockRows): Document;

    /**
     * Re-ancla el documento a otra versión publicada de plantilla (columna
     * `template_version_id`). Usado por la actualización in-situ de versión.
     */
    public function updateTemplateVersionAnchor(string $documentId, string $templateVersionId): void;

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
     * `created_at` de la primera revisión del documento (la más antigua), o null
     * si no hay ninguna revisión. Tipo concreto depende del driver: string ISO o
     * Carbon-compatible. El caller debe normalizar.
     */
    public function firstReviewCreatedAt(string $documentId): mixed;

    /**
     * Guarda una revisión del documento.
     */
    public function saveReview(DocumentReview $review): void;

    /**
     * Aprueba una revisión (actualiza estado y timestamp).
     */
    public function approveReview(string $reviewId): void;

    /**
     * Rechaza una revisión (actualiza estado, timestamp y razón).
     */
    public function rejectReview(string $reviewId, ?string $rejectionReason = null): void;

    /**
     * Listado paginado de documentos con filtros de dominio (ADR-C).
     *
     * Aplica el scope global `user_access` del modelo para garantizar visibilidad.
     *
     * @return LengthAwarePaginator<Document>
     */
    public function paginate(DocumentFilterDto $filter): LengthAwarePaginator;

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
     * Mayor número de versión de snapshot publicado en entity_versions (0 si no hay ninguna).
     */
    public function maxDocumentVersionNumber(string $documentId): int;

    /**
     * Mayor número de versión en document_versions (incluye submitted, published, rejected). 0 si no hay ninguna.
     */
    public function maxDocumentVersionHistoryNumber(string $documentId): int;

    /**
     * Inserta un registro append-only en document_versions.
     *
     * @param  array<string, mixed>|null  $snapshotData  Null si el snapshot canónico está solo en entity_versions.
     */
    public function insertDocumentVersion(
        string $documentId,
        int $versionNumber,
        string $triggerEvent,
        string $triggeredBy,
        ?array $snapshotData,
        ?string $notes = null,
        ?string $entityVersionId = null,
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
     * Última fila de {@see DocumentVersion} con trigger_event «published».
     */
    public function findLatestPublishedDocumentVersion(string $documentId): ?DocumentVersion;

    /**
     * Todas las filas de document_versions para un documento, ordenadas descendentemente por version_number.
     *
     * @return Collection<int, DocumentVersion>
     */
    public function findLegacyDocumentVersionsOrderedDesc(string $documentId): Collection;

    /**
     * Contexto académico de módulo para creación documental.
     *
     * @return array{module_id: string, study_id: string, study_type_id: ?string}|null
     */
    public function findModuleContext(string $moduleId): ?array;

    /**
     * Obtiene los owner_id únicos de documentos activos creados desde una plantilla.
     *
     * @return list<string>
     */
    public function ownerIdsByTemplate(string $templateId, string $status = 'active'): array;

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

    /**
     * IDs de documentos (de entre los indicados) en los que el usuario es revisor asignado.
     *
     * @param  list<string>  $documentIds
     * @return list<string>
     */
    public function findAssignedReviewerDocumentIds(array $documentIds, string $reviewerId): array;

    /**
     * Indica si el usuario tiene una fila en document_reviews para el documento dado,
     * sin importar el estado de la revisión (pending, approved, rejected).
     */
    public function isReviewerAssignedToDocument(string $documentId, string $reviewerId): bool;

    /**
     * Busca un documento por su ID con control de acceso (scope user_access),
     * o lanza ModelNotFoundException. Para usar en operaciones que necesitan autorización.
     */
    public function findByIdWithAccessControl(string $id): Document;

    /**
     * Busca los bloques de un documento ordenados por sort_order, con solo columnas de contenido.
     * Para uso en exportación/renderizado.
     *
     * @return Collection<int, DocumentBlock>
     */
    public function findBlocksForExport(string $documentId): Collection;

    /**
     * Persiste el changelog de envío a validación en la versión de trabajo (head).
     */
    public function updateHeadVersionChangelog(string $documentId, string $changelog): void;

    /**
     * Elimina el changelog de envío de la versión de trabajo (head).
     */
    public function clearHeadVersionChangelog(string $documentId): void;

    /**
     * Carga los bloques y revisiones del documento para construcción de snapshot.
     * Los bloques se devuelven ordenados por sort_order; las revisiones por stage, created_at.
     *
     * @return array{
     *     blocks: list<array{id: mixed, template_block_id: mixed, content: mixed, is_filled: bool, sort_order: int, last_edited_by: mixed, locked_by: mixed, locked_at: ?string}>,
     *     reviews: list<array{reviewer_id: string, stage: int|null, status: string}>
     * }
     */
    public function loadBlocksAndReviewsData(string $documentId): array;
}
