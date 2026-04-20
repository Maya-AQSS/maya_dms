<?php

namespace App\Repositories\Eloquent;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentRepository implements DocumentRepositoryInterface
{
    /**
     * Busca un documento por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Document
    {
        return Document::query()->findOrFail($id);
    }

    /**
     * Crea el documento y sus bloques iniciales en una transacción.
     *
     * @param  array<string, mixed>  $documentAttributes
     * @param  list<array{template_block_id: string, content: mixed, sort_order: int}>  $blockRows
     */
    public function createDocumentWithBlocks(array $documentAttributes, array $blockRows): Document
    {
        return DB::transaction(function () use ($documentAttributes, $blockRows) {
            $document = Document::query()->create($documentAttributes);

            if ($blockRows !== []) {
                $now = now();
                $rowsToInsert = array_map(fn (array $row) => [
                    'id' => (string) Str::uuid(),
                    'document_id' => $document->getKey(),
                    'template_block_id' => $row['template_block_id'],
                    'content' => $row['content'],
                    'is_filled' => false,
                    'sort_order' => $row['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $blockRows);

                DocumentBlock::query()->insert($rowsToInsert);
            }

            return $document->fresh(['blocks']);
        });
    }

    /**
     * Listado de revisiones del documento ordenadas por etapa.
     */
    public function listReviewsForDocument(string $documentId): Collection
    {
        return DocumentReview::query()
            ->where('document_id', $documentId)
            ->orderBy('stage')
            ->get();
    }

    /**
     * Busca una revisión por ID si pertenece al documento indicado.
     */
    public function findReviewInDocument(string $reviewId, string $documentId): ?DocumentReview
    {
        return DocumentReview::query()
            ->where('id', $reviewId)
            ->where('document_id', $documentId)
            ->first();
    }

    /**
     * Elimina todas las revisiones de un documento.
     */
    public function deleteReviewsForDocument(string $documentId): void
    {
        DocumentReview::query()->where('document_id', $documentId)->delete();
    }

    /**
     * Crea revisiones pendientes para un documento.
     *
     * @param  list<array{reviewer_id: string, stage: int}>  $rows
     */
    public function createPendingReviews(string $documentId, array $rows): void
    {
        foreach ($rows as $row) {
            DocumentReview::forceCreate([
                'id' => (string) Str::uuid(),
                'document_id' => $documentId,
                'reviewer_id' => $row['reviewer_id'],
                'stage' => $row['stage'],
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Cuenta las revisiones pendientes de un documento.
     */
    public function countPendingReviewsForDocument(string $documentId): int
    {
        return DocumentReview::query()
            ->where('document_id', $documentId)
            ->where('status', 'pending')
            ->count();
    }

    public function minPendingReviewStageForDocument(string $documentId): ?int
    {
        $min = DocumentReview::query()
            ->where('document_id', $documentId)
            ->where('status', 'pending')
            ->min('stage');

        return $min !== null ? (int) $min : null;
    }

    /**
     * Guarda una revisión del documento.
     */
    public function saveReview(DocumentReview $review): void
    {
        $review->save();
    }

    /**
     * Lista documentos visibles para el usuario actual ordenados por fecha de creación descendente.
     */
    public function listOrderedByCreatedAtDesc(): Collection
    {
        return Document::query()
            ->with('template')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Indica si el usuario es autor (owner_id / created_by) o revisor asignado
     * del documento. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrReviewer(string $documentId, string $userId): bool
    {
        $isAuthor = DB::table('documents')
            ->where('id', $documentId)
            ->where(fn ($q) => $q
                ->where('owner_id', $userId)
                ->orWhere('created_by', $userId)
            )
            ->exists();

        if ($isAuthor) {
            return true;
        }

        return DB::table('document_reviews')
            ->where('document_id', $documentId)
            ->where('reviewer_id', $userId)
            ->exists();
    }
}
