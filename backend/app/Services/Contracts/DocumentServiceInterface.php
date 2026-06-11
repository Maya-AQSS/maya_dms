<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Documents\ApplyTemplateMigrationDto;
use App\DTOs\Documents\BlockDisplayDto;
use App\DTOs\Documents\BlockUpdateDto;
use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\DTOs\Documents\DocumentDto;
use App\DTOs\Documents\DocumentFilterDto;
use App\DTOs\Documents\DocumentMigrationPayloadDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\DTOs\Versioning\WorkingRevisionConflictDto;
use App\Http\Controllers\Api\DocumentController;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\EntityVersion;
use Illuminate\Support\Collection;
use Maya\Http\Pagination\PaginatedDto;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Excepción B4 documentada: la mayoría de métodos de mutación devuelven el
 * Model Eloquent (no DTO). Razón: el {@see DocumentController}
 * adjunta atributos derivados (`can_clone`, `review_mode`, `is_shared_with_me`,
 * etc.) mediante `setAttribute()` sobre el Model resultante antes de presentar
 * como DTO. La conversión final a DTO se hace en el Controller con
 * `DocumentDto::fromModel($model)` antes de pasar al Resource (que es
 * `DocumentDto`-only estricto).
 */
interface DocumentServiceInterface
{
    /**
     * Devuelve el DTO de un documento. Lanza ModelNotFoundException si no existe.
     */
    public function findOrFail(string $id): DocumentDto;

    /**
     * Devuelve el modelo Eloquent del documento. Variante de uso interno cuando
     * el caller necesita el Model para `authorize($ability, $model)`, adjuntar
     * atributos derivados con `setAttribute()`, o encadenar a `update`/`delete`
     * de este mismo Service. Resto de consumidores deben usar `findOrFail()`.
     */
    public function findModelOrFail(string $id): Document;

    public function findModelOrFailWithoutUserAccess(string $id): Document;

    /**
     * Resuelve el modelo de documento aplicando el fallback a snapshot publicado:
     * intenta acceso normal (findModelOrFail); si no existe para el usuario actual,
     * resuelve sin scope de acceso y lanza 404 si no tiene snapshot publicado.
     *
     * Encapsula el patrón try/catch repetido en DocumentVersionController (×2) y
     * DocumentExportController::resolveDocumentForHistory(). El gate `viewHistory`
     * y cualquier comprobación de proceso se aplican en el caller tras esta llamada.
     *
     * @throws HttpException 404 si no existe
     *                       ningún snapshot publicado accesible.
     */
    public function resolveDocumentWithPublishedFallback(string $id): Document;

    public function hasPublishedSnapshot(string $id): bool;

    public function findLatestPublishedVersion(string $documentId): ?EntityVersion;

    /**
     * Crea un documento a partir de un DTO. Devuelve Model — ver excepción B4
     * documentada en el docblock del interface.
     */
    public function create(CreateDocumentDto $dto): Document;

    /**
     * Clona un documento visible hacia un nuevo borrador con el mismo ancla
     * de plantilla y contenido de bloques copiado. Devuelve Model.
     */
    public function clone(string $sourceDocumentId, string $actorId): Document;

    /**
     * Actualiza metadatos editables del documento. Devuelve Model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $documentId, array $attributes): Document;

    /**
     * Borrado lógico del documento.
     */
    public function delete(string $documentId, string $actorId): void;

    /**
     * Bloques para mostrar/editar: definición según {@see Document::$template_version_id} y contenido en document_blocks.
     *
     * @return list<BlockDisplayDto>
     */
    public function blocksForDisplay(Document $document): array;

    /**
     * Actualiza el contenido de un bloque de documento.
     */
    public function updateBlock(UpdateDocumentBlockDto $dto): BlockUpdateDto;

    /**
     * Elimina un bloque opcional de un documento en borrador.
     */
    public function deleteOptionalBlock(DeleteDocumentBlockDto $dto): void;

    /**
     * Transiciona el documento a un nuevo estado. Devuelve Model.
     *
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = []): Document;

    /**
     * Envia el documento a revisión. Devuelve Model.
     */
    public function submitToReview(string $documentId, string $actorId, string $changelog): Document;

    /**
     * Publica el documento. Devuelve Model.
     */
    public function publishDocument(string $documentId, string $actorId, ?string $changelog): Document;

    /**
     * Publicado → borrador para iniciar un nuevo ciclo de edición/revisión. Devuelve Model.
     */
    public function startNewRevisionCycle(string $documentId, string $actorId): Document;

    /**
     * Actualiza in-situ un documento (en ciclo de nueva versión, borrador) a una versión
     * de plantilla más reciente: re-ancla `template_version_id` y reconcilia los bloques
     * (crea los nuevos, aplica el contenido migrado salvo en locked, y elimina/mantiene
     * los removidos según la elección). Devuelve el Model refrescado.
     */
    public function applyTemplateMigration(ApplyTemplateMigrationDto $dto): Document;

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación. Devuelve Model.
     */
    public function destroyVersion(string $documentId, string $versionId, string $actorId): Document;

    /**
     * Delega la propiedad del documento a otro usuario. Devuelve Model.
     */
    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId): Document;

    /**
     * Lista las revisiones del documento. Devuelve Collection<DocumentReview>
     * (Eloquent) — uso interno del Controller para `ReviewResource`. Aplicable
     * a la misma excepción B4 documentada arriba.
     *
     * @return Collection<int, DocumentReview>
     */
    public function listReviews(string $documentId): Collection;

    /**
     * Aprueba una revisión del documento. Devuelve Model.
     */
    public function approveReview(string $documentId, string $reviewId, string $actorId, ?string $publicationChangelog = null): Document;

    /**
     * Localiza una versión snapshot del documento por id (legacy o polimórfico).
     * Devuelve datos de render; la lógica de versión vive en el Service, no en el Controller.
     *
     * @return array{
     *   id: string,
     *   document_id: string,
     *   version_number: int,
     * }
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): array;

    /**
     * Detalle de versión del documento aceptando id legacy o id polimórfico.
     *
     * @return array{
     *   id: string,
     *   document_id: string,
     *   version_number: int,
     *   trigger_event: string,
     *   triggered_by: string,
     *   changelog: ?string,
     *   snapshot_data: array<string, mixed>,
     *   created_at: ?string
     * }
     */
    public function findDocumentVersionDetailOrFail(string $documentId, string $versionId): array;

    /**
     * Metadatos de versiones del documento ordenados descendentemente.
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
    public function listDocumentVersions(string $documentId): array;

    /**
     * Rechaza una revisión del documento. Devuelve Model.
     */
    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): Document;

    /**
     * Listado paginado de documentos con filtros de dominio (ADR-C).
     *
     * @param  callable(Collection<int, Document>): void|null  $beforeMap
     * @return PaginatedDto<DocumentDto>
     */
    public function paginate(
        DocumentFilterDto $filter,
        string $viewerId,
        ?callable $beforeMap = null,
    ): PaginatedDto;

    /**
     * Lista documentos visibles para el usuario actual. Devuelve Collection<Document>
     * (Eloquent) porque el Controller adjunta `can_clone`, `is_shared_with_me`,
     * `team`, etc. a cada item antes de presentar como DTO.
     *
     * @return Collection<int, Document>
     */
    public function listOrderedByCreatedAtDesc(?string $processId = null): Collection;

    /**
     * Opciones de creación de documento disponibles para un módulo.
     *
     * @return list<array{
     *   template_id: string,
     *   template_version_id: string,
     *   process_id: string,
     *   name: string,
     *   description: ?string,
     *   visibility_level: string,
     *   team_id: ?string,
     *   team_name: ?string
     * }>
     */
    public function creationOptionsForModule(string $moduleId): array;

    /**
     * Crea documento desde la vista de módulo. Devuelve Model.
     */
    public function createFromModule(
        string $moduleId,
        string $creatorId,
        string $processId,
        ?string $templateVersionId = null,
        ?string $deliveryDeadline = null,
    ): Document;

    /**
     * Comparación ligera entre la versión de plantilla anclada al documento y la última publicada.
     *
     * @return array{
     *   current_version: ?array{id: string, version_number: int},
     *   latest_version: ?array{id: string, version_number: int, changelog: string},
     *   has_update: bool,
     *   changelog: ?string
     * }
     */
    public function templateVersionStatus(string $documentId): array;

    /**
     * Payload del paso de migración del wizard: bloques de la última versión de
     * plantilla comparados con la versión anclada al documento origen, con el
     * contenido real del origen. Requiere que exista una versión más reciente.
     */
    public function migrationPayload(string $sourceDocumentId): DocumentMigrationPayloadDto;

    /**
     * Crea o actualiza un compartido del documento (solo titular vía policy en controlador).
     *
     * @return array{user_id: string, permission: string, granted_by: string}
     */
    public function upsertDocumentShare(
        string $documentId,
        string $targetUserId,
        string $permission,
        string $actorId,
    ): array;

    /**
     * Elimina un compartido (idempotente si no existía).
     */
    public function removeDocumentShare(string $documentId, string $targetUserId, string $actorId): void;

    /**
     * Anota en cada documento si el visor accede vía `document_shares` y con qué permiso.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function attachShareMetadataForViewer(Collection $documents, string $viewerId): void;

    /**
     * Adjunta metadatos de última versión publicada por documento para construir vistas fallback.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function attachLatestPublishedVersionMeta(Collection $documents): void;

    public function resolveWorkingRevisionConflict(Document $document): WorkingRevisionConflictDto;

    public function attachWorkingRevisionPresentationMeta(Document $document): void;

    /**
     * Adjunta `template_version_number` en lote para evitar resolución por documento en el Resource.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function attachTemplateVersionNumbers(Collection $documents): void;

    /**
     * @param  Collection<int, Document>  $documents
     */
    public function attachIsAssignedReviewerMeta(Collection $documents, string $viewerId): void;

    /**
     * Resuelve el contexto de visibilidad para el endpoint `show` de Document.
     *
     * Determina si el viewer debe recibir el snapshot publicado o el contenido vivo,
     * y si es revisor asignado activo.
     *
     * @return array{serve_published_snapshot: bool, is_assigned_reviewer: bool}
     */
    public function resolveDocumentViewerContext(Document $resolved, string $documentId, string $viewerId): array;

    /**
     * Pool de validadores efectivo del documento (misma fuente que el envío a revisión),
     * para mostrar en el wizard sin requerir acceso de lectura a la plantilla.
     *
     * @return array{
     *   kind: 'document'|'template_fallback'|'none',
     *   review_mode: string,
     *   reviewers: list<array{id: string, name: ?string, stage: ?int}>
     * }
     */
    public function getDocumentReviewerPool(Document $document): array;
}
