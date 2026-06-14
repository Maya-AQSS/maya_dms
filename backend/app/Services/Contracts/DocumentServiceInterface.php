<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Documents\ApplyTemplateMigrationDto;
use App\DTOs\Documents\BlockDisplayDto;
use App\DTOs\Documents\BlockUpdateDto;
use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\CreationOptionDto;
use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\DTOs\Documents\DocumentDto;
use App\DTOs\Documents\DocumentFilterDto;
use App\DTOs\Documents\DocumentMigrationPayloadDto;
use App\DTOs\Documents\DocumentReviewDto;
use App\DTOs\Documents\DocumentShareResultDto;
use App\DTOs\Documents\ReviewerPoolDto;
use App\DTOs\Documents\TemplateVersionStatusDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\DTOs\Versioning\DocumentVersionDetailDto;
use App\DTOs\Versioning\DocumentVersionDto;
use App\DTOs\Versioning\DocumentVersionSummaryDto;
use App\DTOs\Versioning\WorkingRevisionConflictDto;
use App\Http\Controllers\Api\DocumentController;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Collection;
use Maya\Http\Pagination\PaginatedDto;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Los métodos de mutación devuelven {@see DocumentDto}. La presentación derivada
 * (`can_clone`, `review_mode`, team embebido, …) que el {@see DocumentController}
 * adjunta con `setAttribute()` sobre el Model se inyecta mediante el callback
 * opcional `$beforeMap` (mismo patrón que {@see self::paginate}), que recibe el
 * Model justo antes de la conversión a DTO.
 *
 * Excepción documentada (mantener): `findModelOrFail*` y los resolvers de Model
 * existen SOLO para `authorize($ability, $model)` con Policies (exigen Model) y
 * para flujos de presentación de `show` que componen meta derivada sobre el
 * modelo resuelto por el FormRequest. Ver changes.md (F4-B1).
 *
 * Excepción R2 deliberada (decisión de arquitectura, no deuda): {@see DocumentPolicy}
 * (18 métodos) inspecciona owner_id/status/process_id/reviewers/scopes sobre el Model ya
 * cargado con sus relaciones. Forzar id/DTO obligaría a re-fetch dentro de cada método de
 * Policy (N+1 en endpoints de lista/bulk que autorizan por-item) o a un DTO espejo del modelo
 * acoplado a las tripas de la Policy. El coste de rendimiento supera al beneficio; estos
 * métodos quedan acotados a autorización (@internal authorization-only).
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
     * Crea un documento a partir de un DTO.
     *
     * @param  callable(Document): void|null  $beforeMap
     */
    public function create(CreateDocumentDto $dto, ?callable $beforeMap = null): DocumentDto;

    /**
     * Clona un documento visible hacia un nuevo borrador con el mismo ancla
     * de plantilla y contenido de bloques copiado.
     *
     * @param  callable(Document): void|null  $beforeMap
     */
    public function clone(string $sourceDocumentId, string $actorId, ?callable $beforeMap = null): DocumentDto;

    /**
     * Actualiza metadatos editables del documento.
     *
     * @param  array<string, mixed>  $attributes
     * @param  callable(Document): void|null  $beforeMap
     */
    public function update(string $documentId, array $attributes, ?callable $beforeMap = null): DocumentDto;

    /**
     * Borrado lógico del documento.
     */
    public function delete(string $documentId, string $actorId): void;

    /**
     * Bloques para mostrar/editar: definición según {@see Document::$template_version_id} y contenido en document_blocks.
     *
     * @return list<BlockDisplayDto>
     */
    public function blocksForDisplay(string $documentId): array;

    /**
     * Actualiza el contenido de un bloque de documento.
     */
    public function updateBlock(UpdateDocumentBlockDto $dto): BlockUpdateDto;

    /**
     * Elimina un bloque opcional de un documento en borrador.
     */
    public function deleteOptionalBlock(DeleteDocumentBlockDto $dto): void;

    /**
     * Transiciona el documento a un nuevo estado.
     *
     * @param  array<string, mixed>  $extraAttributes
     * @param  callable(Document): void|null  $beforeMap
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = [], ?callable $beforeMap = null): DocumentDto;

    /**
     * Envia el documento a revisión. Devuelve Model.
     */
    public function submitToReview(string $documentId, string $actorId, string $changelog): Document;

    /**
     * Publica el documento.
     *
     * @param  callable(Document): void|null  $beforeMap
     */
    public function publishDocument(string $documentId, string $actorId, ?string $changelog, ?callable $beforeMap = null): DocumentDto;

    /**
     * Publicado → borrador para iniciar un nuevo ciclo de edición/revisión.
     *
     * @param  callable(Document): void|null  $beforeMap
     */
    public function startNewRevisionCycle(string $documentId, string $actorId, ?callable $beforeMap = null): DocumentDto;

    /**
     * Actualiza in-situ un documento (en ciclo de nueva versión, borrador) a una versión
     * de plantilla más reciente: re-ancla `template_version_id` y reconcilia los bloques
     * (crea los nuevos, aplica el contenido migrado salvo en locked, y elimina/mantiene
     * los removidos según la elección).
     *
     * @param  callable(Document): void|null  $beforeMap
     */
    public function applyTemplateMigration(ApplyTemplateMigrationDto $dto, ?callable $beforeMap = null): DocumentDto;

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación. Devuelve Model.
     */
    public function destroyVersion(string $documentId, string $versionId, string $actorId): Document;

    /**
     * Delega la propiedad del documento a otro usuario.
     *
     * @param  callable(Document): void|null  $beforeMap
     */
    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId, ?callable $beforeMap = null): DocumentDto;

    /**
     * Lista las revisiones del documento.
     *
     * @return Collection<int, DocumentReviewDto>
     */
    public function listReviews(string $documentId): Collection;

    /**
     * Aprueba una revisión del documento.
     */
    public function approveReview(string $documentId, string $reviewId, string $actorId, ?string $publicationChangelog = null): DocumentDto;

    /**
     * Localiza una versión snapshot del documento por id (legacy o polimórfico).
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersionDto;

    /**
     * Detalle de versión del documento aceptando id legacy o id polimórfico.
     */
    public function findDocumentVersionDetailOrFail(string $documentId, string $versionId): DocumentVersionDetailDto;

    /**
     * Metadatos de versiones del documento ordenados descendentemente.
     *
     * @return list<DocumentVersionSummaryDto>
     */
    public function listDocumentVersions(string $documentId): array;

    /**
     * Rechaza una revisión del documento.
     */
    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): DocumentDto;

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
     * Lista documentos visibles para el usuario actual ordenados por creación desc.
     *
     * @return Collection<int, DocumentDto>
     */
    public function listOrderedByCreatedAtDesc(?string $processId = null): Collection;

    /**
     * Opciones de creación de documento disponibles para un módulo.
     *
     * @return list<CreationOptionDto>
     */
    public function creationOptionsForModule(string $moduleId): array;

    /**
     * Crea documento desde la vista de módulo.
     *
     * @param  callable(Document): void|null  $beforeMap
     */
    public function createFromModule(
        string $moduleId,
        string $creatorId,
        string $processId,
        ?string $templateVersionId = null,
        ?string $deliveryDeadline = null,
        ?callable $beforeMap = null,
    ): DocumentDto;

    /**
     * Comparación ligera entre la versión de plantilla anclada al documento y la última publicada.
     */
    public function templateVersionStatus(string $documentId): TemplateVersionStatusDto;

    /**
     * Payload del paso de migración del wizard: bloques de la última versión de
     * plantilla comparados con la versión anclada al documento origen, con el
     * contenido real del origen. Requiere que exista una versión más reciente.
     */
    public function migrationPayload(string $sourceDocumentId): DocumentMigrationPayloadDto;

    /**
     * Crea o actualiza un compartido del documento (solo titular vía policy en controlador).
     */
    public function upsertDocumentShare(
        string $documentId,
        string $targetUserId,
        string $permission,
        string $actorId,
    ): DocumentShareResultDto;

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
     */
    public function getDocumentReviewerPool(Document $document): ReviewerPoolDto;
}
