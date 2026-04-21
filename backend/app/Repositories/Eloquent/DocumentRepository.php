<?php

namespace App\Repositories\Eloquent;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
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
     * Busca un bloque por su ID dentro del documento o lanza ModelNotFoundException.
     */
    public function findBlockInDocumentOrFail(string $documentId, string $blockId): DocumentBlock
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->where('id', $blockId)
            ->firstOrFail();
    }

    /**
     * Guarda un bloque del documento.
     */
    public function saveBlock(DocumentBlock $block): void
    {
        $block->save();
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

    /**
     * Mayor número de versión de snapshot guardado para el documento.
     */
    public function maxDocumentVersionNumber(string $documentId): int
    {
        $max = DocumentVersion::query()
            ->where('document_id', $documentId)
            ->max('version_number');

        return $max !== null ? (int) $max : 0;
    }

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
    ): void {
        DocumentVersion::forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'version_number' => $versionNumber,
            'trigger_event' => $triggerEvent,
            'triggered_by' => $triggeredBy,
            'snapshot_data' => $snapshotData,
            'notes' => $notes,
            'is_immutable' => true,
            'created_at' => now(),
        ]);
    }

    /**
     * Busca una versión de documento por su ID dentro del documento o lanza ModelNotFoundException.
     */
    public function findDocumentVersionInDocumentOrFail(string $documentId, string $versionId): DocumentVersion
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->where('id', $versionId)
            ->firstOrFail();
    }

    /**
     * Crea o actualiza un compartido del documento (solo titular vía policy en controlador).
     */
    public function upsertDocumentShare(
        string $documentId,
        string $userId,
        string $permission,
        string $grantedBy,
    ): void {
        $existing = DocumentShare::query()
            ->where('document_id', $documentId)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'permission' => $permission,
                'granted_by' => $grantedBy,
            ]);

            return;
        }

        DocumentShare::forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'user_id' => $userId,
            'permission' => $permission,
            'granted_by' => $grantedBy,
        ]);
    }

    /**
     * Elimina un compartido; no lanza si no existía.
     */
    public function deleteDocumentShare(string $documentId, string $userId): void
    {
        DocumentShare::query()
            ->where('document_id', $documentId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Permisos de compartición del usuario sobre los documentos indicados.
     *
     * @param  list<string>  $documentIds
     * @return array<string, string> mapa document_id => permission (read|edit)
     */
    public function sharePermissionsForViewer(array $documentIds, string $userId): array
    {
        $documentIds = array_values(array_unique(array_filter($documentIds)));
        if ($documentIds === []) {
            return [];
        }

        $rows = DocumentShare::query()
            ->whereIn('document_id', $documentIds)
            ->where('user_id', $userId)
            ->get(['document_id', 'permission']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->document_id] = (string) $row->permission;
        }

        return $out;
    }
}
