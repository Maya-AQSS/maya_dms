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
     * Rechaza el documento.
     */
    public function rejectDocument(string $documentId, string $actorId): Document;

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
     * @return list<array{template_id: string, template_version_id: string, name: string, description: ?string}>
     */
    public function creationOptionsForModule(string $moduleId): array;

    /**
     * Crea documento desde la vista de módulo resolviendo plantilla/version disponibles.
     */
    public function createFromModule(
        string $moduleId,
        string $creatorId,
        ?string $templateVersionId = null,
    ): Document;
}
