<?php

namespace App\Repositories\Eloquent;

use App\Models\BlockVersion;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class DocumentRepository implements DocumentRepositoryInterface
{
    /**
     * Busca un documento por su ID o lanza ModelNotFoundException.
     *
     * El alcance global `user_access` incluye revisores en `document_reviews`; si por cualquier
     * desajuste el documento no entra en la consulta acotada, se comprueba una asignación pendiente
     * explícita y se carga sin ese alcance (misma condición que usa la bandeja del dashboard).
     */
    public function findOrFail(string $id): Document
    {
        $scoped = Document::query()->with(['templateVersion'])->whereKey($id)->first();
        if ($scoped !== null) {
            return $scoped;
        }

        if (auth()->check()) {
            $uid = (string) auth()->user()->getAuthIdentifier();
            if ($uid !== '') {
                $assigned = DocumentReview::query()
                    ->where('document_id', $id)
                    ->where('reviewer_id', $uid)
                    ->where('status', 'pending')
                    ->exists();

                if ($assigned) {
                    return Document::withoutGlobalScopes(['user_access'])
                        ->with(['templateVersion'])
                        ->whereKey($id)
                        ->firstOrFail();
                }
            }
        }

        return Document::query()->with(['templateVersion'])->whereKey($id)->firstOrFail();
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
                    'content' => $this->encodeDocumentBlockContentForInsert($row['content']),
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
     * Elimina todas las revisiones de un documento (uso en submitToReview para ciclo limpio).
     */
    public function deleteReviewsForDocument(string $documentId): void
    {
        DocumentReview::query()->where('document_id', $documentId)->delete();
    }

    /**
     * Elimina solo las revisiones pendientes, conservando las rechazadas como historial.
     */
    public function deletePendingReviewsForDocument(string $documentId): void
    {
        DocumentReview::query()
            ->where('document_id', $documentId)
            ->where('status', 'pending')
            ->delete();
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
            ->select(['documents.*', 'owner_user.name as owner_name'])
            ->leftJoin('users as owner_user', 'owner_user.id', '=', 'documents.owner_id')
            ->with(['template', 'templateVersion'])
            ->orderByDesc('documents.created_at')
            ->get();
    }

    /**
     * Bandeja de validación de documentos pendiente para un revisor (documento en revisión y fila
     * `document_reviews` pending). En modo secuencial de la plantilla solo entran revisiones cuya
     * etapa coincide con la menor etapa aún pendiente del documento.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listPendingDocumentReviewInboxForUser(string $userId): Collection
    {
        $minPendingByDocument = DB::table('document_reviews')
            ->select('document_id')
            ->selectRaw('MIN(stage) as min_stage')
            ->where('status', 'pending')
            ->groupBy('document_id');

        $rows = DB::table('document_reviews as dr')
            ->join('documents as d', 'd.id', '=', 'dr.document_id')
            ->join('templates as t', 't.id', '=', 'd.template_id')
            ->leftJoin('users as owner_user', 'owner_user.id', '=', 'd.owner_id')
            ->leftJoinSub($minPendingByDocument, 'ps', function ($join) {
                $join->on('ps.document_id', '=', 'd.id');
            })
            ->where('dr.reviewer_id', $userId)
            ->where('dr.status', 'pending')
            ->where('d.status', 'in_review')
            ->where(function ($q) {
                $q->whereNull('t.review_mode')
                    ->orWhere('t.review_mode', 'parallel')
                    ->orWhere(function ($q2) {
                        $q2->where('t.review_mode', 'sequential')
                            ->whereColumn('dr.stage', 'ps.min_stage');
                    });
            })
            ->orderByRaw('CASE WHEN d.delivery_deadline IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('d.delivery_deadline', 'asc')
            ->orderByDesc('d.updated_at')
            ->get([
                'd.id as document_id',
                'd.title',
                'd.owner_id',
                'd.delivery_deadline',
                'd.status',
                'dr.id as review_id',
                'dr.stage',
                't.review_mode',
                'owner_user.name as owner_name',
            ]);

        $today = Carbon::today();

        return $rows->map(function (object $row) use ($today): array {
            $deadlineIso = null;
            $daysRemaining = null;
            if ($row->delivery_deadline !== null) {
                $deadline = Carbon::parse((string) $row->delivery_deadline);
                $deadlineIso = $deadline->toIso8601String();
                $daysRemaining = (int) round((float) $today->diffInDays($deadline, false));
            }

            return [
                'document_id' => (string) $row->document_id,
                'review_id' => (string) $row->review_id,
                'title' => (string) $row->title,
                'owner_id' => (string) $row->owner_id,
                'owner_name' => $row->owner_name !== null && $row->owner_name !== ''
                    ? (string) $row->owner_name
                    : null,
                'delivery_deadline' => $deadlineIso,
                'days_remaining' => $daysRemaining,
                'status' => (string) $row->status,
                'review_stage' => (int) $row->stage,
                'review_mode' => (string) ($row->review_mode ?? 'parallel'),
            ];
        })->values();
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

    /**
     * Mayor número de versión de snapshot guardado para el bloque del documento.
     */
    public function maxBlockVersionNumberForDocumentBlock(string $documentBlockId): int
    {
        $max = BlockVersion::query()
            ->where('document_block_id', $documentBlockId)
            ->max('version_number');

        return $max !== null ? (int) $max : 0;
    }

    /**
     * Inserta un registro append-only en block_versions.
     *
     * @param  array<string, mixed>  $snapshotData
     */
    public function insertDocumentBlockVersion(
        string $documentBlockId,
        string $documentId,
        int $versionNumber,
        array $content,
        ?array $diff,
        string $editedBy,
    ): void {
        BlockVersion::forceCreate([
            'id' => (string) Str::uuid(),
            'document_block_id' => $documentBlockId,
            'document_id' => $documentId,
            'version_number' => $versionNumber,
            'content' => $content,
            'diff' => $diff,
            'edited_by' => $editedBy,
            'created_at' => now(),
        ]);
    }

    /**
     * Serializa contenido para columnas JSON en insert masivo (insert() no aplica casts de Eloquent).
     *
     * @throws JsonException
     */
    private function encodeDocumentBlockContentForInsert(mixed $content): ?string
    {
        if ($content === null) {
            return null;
        }
        if (is_string($content)) {
            return $content;
        }

        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
