<?php

namespace App\Repositories\Eloquent;

use App\Models\Document;
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
        return Document::findOrFail($id);
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
                'id'            => (string) Str::uuid(),
                'document_id'   => $documentId,
                'reviewer_id'   => $row['reviewer_id'],
                'stage'         => $row['stage'],
                'status'        => 'pending',
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

    public function saveReview(DocumentReview $review): void
    {
        $review->save();
    }

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
