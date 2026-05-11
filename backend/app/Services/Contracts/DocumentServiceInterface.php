<?php

namespace App\Services\Contracts;

use App\DTOs\Documents\CreateDocumentDto;
use App\DTOs\Documents\DeleteDocumentBlockDto;
use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentVersion;
use Illuminate\Support\Collection;

interface DocumentServiceInterface
{
    /**
     * Localiza un documento por su ID.
     */
    public function findOrFail(string $id): Document;

    /**
     * Crea un documento a partir de un DTO.
     */
    public function create(CreateDocumentDto $dto): Document;

    /**
     * Clona un documento visible hacia un nuevo borrador con el mismo ancla de plantilla y contenido de bloques copiado.
     */
    public function clone(string $sourceDocumentId, string $actorId): Document;

    /**
     * Actualiza metadatos editables del documento.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $documentId, array $attributes): Document;

    /**
     * Borrado lógico del documento.
     */
    public function delete(string $documentId): void;

    /**
     * Bloques para mostrar/editar: definición según {@see Document::$template_version_id} y contenido en document_blocks.
     *
     * @return list<array<string, mixed>>
     */
    public function blocksForDisplay(Document $document): array;

    /**
     * Actualiza el contenido de un bloque de documento.
     */
    public function updateBlock(UpdateDocumentBlockDto $dto): array;

    /**
     * Elimina un bloque opcional de un documento en borrador.
     * Solo permite bloques con block_state === 'optional'.
     */
    public function deleteOptionalBlock(DeleteDocumentBlockDto $dto): void;

    /**
     * Transiciona el documento a un nuevo estado y emite el evento de dominio DocumentStateChanged.
     *
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = []): Document;

    /**
     * Envia el documento a revisión.
     */
    public function submitToReview(string $documentId, string $actorId): Document;

    /**
     * Publica el documento.
     */
    public function publishDocument(string $documentId, string $actorId, ?string $changelog): Document;

    /**
     * Publicado → borrador para iniciar un nuevo ciclo de edición/revisión antes de volver a publicar.
     */
    public function startNewRevisionCycle(string $documentId, string $actorId): Document;

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación.
     */
    public function destroyVersion(string $documentId, string $versionId, string $actorId): Document;

    /**
     * Delega la propiedad del documento a otro usuario.
     */
    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId): Document;

    /**
     * Lista las revisiones del documento.
     * 
     * @return Collection<int, DocumentReview>
     */
    public function listReviews(string $documentId): Collection;

    /**
     * Aprueba una revisión del documento.
     */
    public function approveReview(string $documentId, string $reviewId, string $actorId, ?string $publicationChangelog = null): Document;

    /**
     * Localiza una versión snapshot del documento por id.
     */
    public function findDocumentVersionOrFail(string $documentId, string $versionId): DocumentVersion;

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
     * Rechaza una revisión del documento.
     */
    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): Document;

    /**
     * Lista documentos visibles para el usuario actual ordenados por fecha de creación descendente.
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
     * Crea documento desde la vista de módulo resolviendo plantilla/version disponibles.
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
     * Anota en cada documento si el visor accede vía `document_shares` y con qué permiso (listado / detalle).
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

    /**
     * Adjunta `template_version_number` en lote para evitar resolución por documento en el Resource.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function attachTemplateVersionNumbers(Collection $documents): void;
}
