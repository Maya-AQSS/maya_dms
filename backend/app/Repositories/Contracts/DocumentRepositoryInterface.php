<?php

namespace App\Repositories\Contracts;

use App\Models\Document;
use App\Models\DocumentReview;
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
}
