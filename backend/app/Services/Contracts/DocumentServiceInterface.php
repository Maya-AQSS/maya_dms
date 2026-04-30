<?php

namespace App\Services\Contracts;

use App\DTOs\Documents\CreateDocumentDto;
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
    public function publishDocument(string $documentId, string $actorId, string $changelog): Document;

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
    public function listOrderedByCreatedAtDesc(): Collection;

    /**
     * Opciones de creación de documento disponibles para un módulo.
     *
     * @return list<array{template_id: string, template_version_id: string, process_id: string, name: string, description: ?string}>
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
}
