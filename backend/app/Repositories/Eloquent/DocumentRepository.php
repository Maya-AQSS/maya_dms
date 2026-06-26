<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Documents\DocumentBlockPayloadDto;
use App\DTOs\Documents\DocumentFilterDto;
use App\DTOs\Notifications\ApproachingDeadlineDocumentDto;
use App\DTOs\Notifications\PendingReviewerLoadDto;
use App\Models\BlockVersion;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentReview;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\EntityVersion;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Support\DocumentHeadSnapshot;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class DocumentRepository extends AbstractVersionableEntityRepository implements DocumentRepositoryInterface
{
    // ─── AbstractVersionableEntityRepository helpers ──────────────────────────

    protected function pendingReviewModelClass(): string
    {
        return DocumentReview::class;
    }

    protected function pendingReviewForeignKey(): string
    {
        return 'document_id';
    }

    // ─────────────────────────────────────────────────────────────────────────
    /**
     * Busca un documento por su ID aplicando el scope `user_access`, o lanza ModelNotFoundException.
     *
     * La visibilidad la gestiona íntegramente el scope global del modelo:
     * propietario, compartido, revisor en ciclo activo o catálogo publicado con contexto académico.
     */
    public function findOrFail(string $id): Document
    {
        return Document::query()
            ->with(['templateVersion', 'headVersion'])
            ->withExists(['reviews as has_review_comments' => fn ($q) => $q->where('status', 'rejected')])
            ->whereKey($id)
            ->firstOrFail();
    }

    public function findOrFailForRefreshAfterMutation(string $id): Document
    {
        return Document::query()
            ->withoutGlobalScope('user_access')
            ->with(['templateVersion', 'headVersion'])
            ->withExists(['reviews as has_review_comments' => fn ($q) => $q->where('status', 'rejected')])
            ->whereKey($id)
            ->firstOrFail();
    }

    public function findWithBlocksAndThemeOrFail(string $id): Document
    {
        $document = Document::query()
            ->withoutGlobalScope('user_access')
            ->with(['blocks' => fn ($q) => $q->orderBy('sort_order'), 'template.theme'])
            ->whereKey($id)
            ->first();

        if ($document === null) {
            throw (new ModelNotFoundException)->setModel(Document::class);
        }

        return $document;
    }

    /**
     * Borrado lógico de documento.
     */
    public function delete(Document $document): void
    {
        $document->delete();
    }

    /**
     * Actualiza metadatos editables del documento.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDocumentMetadata(Document $document, array $attributes): Document
    {
        $deliveryDeadline = array_key_exists('delivery_deadline', $attributes)
            ? $attributes['delivery_deadline']
            : $document->delivery_deadline;

        return $this->mergeHeadWorkingCopy($document, [
            'title' => $attributes['title'] ?? $document->title,
            'delivery_deadline' => $deliveryDeadline,
            'study_type_id' => $attributes['study_type_id'] ?? $document->study_type_id,
            'study_id' => $attributes['study_id'] ?? $document->study_id,
            'module_id' => $attributes['module_id'] ?? $document->module_id,
        ]);
    }

    /**
     * Actualiza owner del documento.
     */
    public function updateOwner(Document $document, string $newOwnerId): Document
    {
        return $this->mergeHeadWorkingCopy($document, ['owner_id' => $newOwnerId]);
    }

    public function mergeHeadWorkingCopy(Document $document, array $updates): Document
    {
        $delegatedFlip = array_flip(DocumentHeadSnapshot::DELEGATED_ATTRIBUTES);
        $headUpdates = array_intersect_key($updates, $delegatedFlip);
        if ($headUpdates === []) {
            return $document->fresh(['headVersion']);
        }

        $document->loadMissing('headVersion');
        $ev = $document->headVersion;
        if ($ev === null) {
            throw new RuntimeException('Documento sin versión cabezal en entity_versions.');
        }

        $normalized = $this->normalizeHeadSnapshotUpdates($headUpdates);
        $ev->snapshot_data = DocumentHeadSnapshot::mergeDocumentKey($ev->snapshot_data ?? [], $normalized);
        if (array_key_exists('status', $headUpdates)) {
            $ev->status = (string) $headUpdates['status'];
        }
        $ev->save();

        return $document->fresh(['headVersion']);
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function normalizeHeadSnapshotUpdates(array $updates): array
    {
        $out = [];
        foreach ($updates as $k => $v) {
            $out[$k] = match ($k) {
                'delivery_deadline' => TemplateHeadSnapshot::normalizeDeadlineForSnapshot($v),
                default => $v,
            };
        }

        return $out;
    }

    /**
     * Crea el documento y sus bloques iniciales en una transacción.
     *
     * @param  array<string, mixed>  $documentAttributes
     * @param  list<array{template_block_id: string, content: mixed, sort_order: int, is_filled?: bool, last_edited_by?: ?string}>  $blockRows
     */
    public function createDocumentWithBlocks(array $documentAttributes, array $blockRows): Document
    {
        return DB::transaction(function () use ($documentAttributes, $blockRows) {
            $delegatedFlip = array_flip(DocumentHeadSnapshot::DELEGATED_ATTRIBUTES);
            $delegated = array_intersect_key($documentAttributes, $delegatedFlip);
            $anchor = array_diff_key($documentAttributes, $delegatedFlip);

            $document = new Document;
            if (! empty($anchor['id'])) {
                $document->setAttribute('id', $anchor['id']);
            }
            $document->process_id = $anchor['process_id'];
            $document->template_id = $anchor['template_id'];
            $document->template_version_id = $anchor['template_version_id'] ?? null;
            $document->save();

            $row = array_merge($delegated, [
                'id' => $document->getKey(),
                'process_id' => $document->process_id,
                'template_id' => $document->template_id,
                'status' => $delegated['status'] ?? 'draft',
            ]);

            $snapshot = DocumentHeadSnapshot::buildPayloadFromLegacyRow(
                $row,
                $document->getKey(),
                (string) $document->process_id,
                (string) $document->template_id,
            );

            $now = now();
            $headId = (string) Str::uuid();

            DB::table('entity_versions')->insert([
                'id' => $headId,
                'versionable_type' => Document::class,
                'versionable_id' => $document->getKey(),
                'version_number' => 0,
                'base_version_id' => null,
                'change_set' => null,
                'status' => (string) ($delegated['status'] ?? 'draft'),
                'created_by' => (string) ($delegated['created_by'] ?? ''),
                'published_by' => null,
                'published_at' => null,
                'changelog' => null,
                'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $document->head_entity_version_id = $headId;
            $document->save();

            if ($blockRows !== []) {
                $now = now();
                $rowsToInsert = array_map(fn (array $row) => [
                    'id' => (string) Str::uuid(),
                    'document_id' => $document->getKey(),
                    'template_block_id' => $row['template_block_id'],
                    'content' => $this->encodeDocumentBlockContentForInsert($row['content']),
                    'is_filled' => array_key_exists('is_filled', $row) ? (bool) $row['is_filled'] : false,
                    'last_edited_by' => array_key_exists('last_edited_by', $row) ? $row['last_edited_by'] : null,
                    'sort_order' => $row['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $blockRows);

                DocumentBlock::query()->insert($rowsToInsert);
            }

            return $document->fresh(['blocks', 'headVersion']);
        });
    }

    public function updateTemplateVersionAnchor(string $documentId, string $templateVersionId): void
    {
        // Sin scopes globales: es una actualización directa de la columna ancla
        // (los scopes añaden JOINs que rompen el UPDATE en Postgres).
        Document::withoutGlobalScopes()
            ->whereKey($documentId)
            ->update(['template_version_id' => $templateVersionId]);
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
            ->with('reviewer:id,name')
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

    public function reviewersWithPendingReviewsAbove(int $threshold): array
    {
        return DocumentReview::query()
            ->selectRaw('reviewer_id, COUNT(*) AS pending_count')
            ->where('status', 'pending')
            ->groupBy('reviewer_id')
            ->havingRaw('COUNT(*) > ?', [$threshold])
            ->get()
            ->map(static fn ($row): PendingReviewerLoadDto => new PendingReviewerLoadDto(
                reviewerId: (string) $row->reviewer_id,
                pendingCount: (int) $row->pending_count,
            ))
            ->values()
            ->all();
    }

    public function findApproachingDeadline(int $days): Collection
    {
        $now = Date::now();
        $deadlineStart = $now->copy();
        $deadlineEnd = $now->copy()->addDays($days);

        $deadlineExpression = DocumentHeadSnapshot::jsonDocumentFieldExpression('ev', 'delivery_deadline');
        $statusExpression = DocumentHeadSnapshot::jsonDocumentFieldExpression('ev', 'status');

        $rows = DB::table('documents')
            ->join('entity_versions as ev', 'ev.id', '=', 'documents.head_entity_version_id')
            ->whereNull('documents.deleted_at')
            ->whereRaw("({$deadlineExpression})::TIMESTAMP BETWEEN ? AND ?", [
                $deadlineStart->toDateTimeString(),
                $deadlineEnd->toDateTimeString(),
            ])
            ->whereRaw("{$statusExpression} != 'published'")
            ->get(['ev.snapshot_data']);

        $dtos = [];
        foreach ($rows as $row) {
            try {
                $snapshot = json_decode((string) $row->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            $docData = $snapshot[DocumentHeadSnapshot::JSON_DOCUMENT_KEY] ?? null;
            if (! is_array($docData)) {
                continue;
            }

            $documentId = $docData['id'] ?? null;
            $ownerId = $docData['owner_id'] ?? null;
            $deadline = $docData['delivery_deadline'] ?? null;

            if (! is_string($documentId) || ! is_string($ownerId) || ! is_string($deadline)
                || $documentId === '' || $ownerId === '' || $deadline === '') {
                continue;
            }

            $title = $docData['title'] ?? null;

            $dtos[] = new ApproachingDeadlineDocumentDto(
                documentId: $documentId,
                title: is_string($title) && $title !== '' ? $title : 'Sin título',
                ownerId: $ownerId,
                deadline: $deadline,
            );
        }

        return new Collection($dtos);
    }

    public function minPendingReviewStageForDocument(string $documentId): ?int
    {
        return $this->minPendingReviewStage($documentId);
    }

    public function firstReviewCreatedAt(string $documentId): mixed
    {
        return DocumentReview::query()
            ->where('document_id', $documentId)
            ->min('created_at');
    }

    /**
     * Guarda una revisión del documento.
     */
    public function saveReview(DocumentReview $review): void
    {
        $review->save();
    }

    /**
     * Aprueba una revisión (actualiza estado y timestamp).
     */
    public function approveReview(string $reviewId): void
    {
        DocumentReview::query()
            ->where('id', $reviewId)
            ->update([
                'status' => 'approved',
                'reviewed_at' => now(),
            ]);
    }

    /**
     * Rechaza una revisión (actualiza estado, timestamp y razón).
     */
    public function rejectReview(string $reviewId, ?string $rejectionReason = null): void
    {
        DocumentReview::query()
            ->where('id', $reviewId)
            ->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);
    }

    /**
     * Listado paginado de documentos visibles para el usuario actual con filtros de dominio.
     *
     * El scope global `user_access` aplica {@see Document::applyCatalogAccessFilter()}.
     *
     * @return LengthAwarePaginator<Document>
     */
    public function paginate(DocumentFilterDto $filter): LengthAwarePaginator
    {
        $query = Document::withoutGlobalScopes(['join_head_document_entity_version'])
            ->join('entity_versions as document_head_ev', 'document_head_ev.id', '=', 'documents.head_entity_version_id')
            ->select(['documents.*', 'owner_user.name as owner_name'])
            ->leftJoin('users as owner_user', function ($join) {
                $join->whereRaw(
                    'owner_user.id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id')
                );
            })
            ->withExists(['reviews as has_review_comments' => fn ($q) => $q->where('status', 'rejected')])
            ->with(['template', 'templateVersion']);

        if ($filter->processId !== null) {
            $query->where('documents.process_id', $filter->processId);
        }

        if ($filter->status !== null) {
            $query->whereRaw(
                DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'status').' = ?',
                [$filter->status]
            );
        }

        if ($filter->templateId !== null) {
            $query->where('documents.template_id', $filter->templateId);
        }

        if ($filter->createdBy !== null) {
            $query->whereRaw(
                DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'created_by').' = ?',
                [$filter->createdBy]
            );
        }

        if ($filter->favoriteIds !== null && $filter->favoriteIds !== []) {
            // Favoritos de documento se referencian por el id del propio documento.
            $query->whereIn('documents.id', $filter->favoriteIds);
        }

        // Contexto académico (snapshot del cabezal): filtro estructurado en cascada.
        // Se aplican como AND independientes; el selector en cascada garantiza
        // que los valores padres acompañan siempre al hijo más específico.
        if ($filter->studyTypeId !== null) {
            $query->whereRaw(
                DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'study_type_id').' = ?',
                [$filter->studyTypeId]
            );
        }

        if ($filter->studyId !== null) {
            $query->whereRaw(
                DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'study_id').' = ?',
                [$filter->studyId]
            );
        }

        if ($filter->moduleId !== null) {
            $query->whereRaw(
                DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'module_id').' = ?',
                [$filter->moduleId]
            );
        }

        if ($filter->from !== null) {
            $query->whereDate('documents.created_at', '>=', $filter->from);
        }

        if ($filter->to !== null) {
            $query->whereDate('documents.created_at', '<=', $filter->to);
        }

        if ($filter->search !== null && trim($filter->search) !== '') {
            $query->whereRaw(
                DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'title').' ILIKE ?',
                ['%'.str_replace(['%', '_'], ['\\%', '\\_'], $filter->search).'%']
            );
        }

        $this->applyDocumentSort($query, $filter->sortBy, $filter->sortDir);

        return $query->paginate($filter->perPage, ['*'], 'page', $filter->page);
    }

    /**
     * Ordenación server-side con whitelist. Las columnas en el snapshot JSON
     * (title, status, delivery_deadline) se ordenan por su expresión JSON, que
     * es la fuente de verdad. Cualquier valor fuera de la whitelist cae al
     * default (created_at desc).
     *
     * Columnas permitidas: created_at, updated_at, title, status, delivery_deadline.
     *
     * @param  Builder<Document>  $query
     */
    private function applyDocumentSort($query, ?string $sortBy, string $sortDir): void
    {
        $dir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';
        $jsonField = fn (string $f): string => DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', $f);

        switch ($sortBy) {
            case 'updated_at':
                $query->orderBy('documents.updated_at', $dir);
                break;
            case 'title':
                $query->orderByRaw($jsonField('title').' '.$dir);
                break;
            case 'status':
                $query->orderByRaw($jsonField('status').' '.$dir);
                break;
            case 'delivery_deadline':
                $deadline = $jsonField('delivery_deadline');
                // Documentos sin plazo van al final independientemente de la dirección.
                $query->orderByRaw('CASE WHEN '.$deadline.' IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderByRaw($deadline.' '.$dir);
                break;
            case 'created_at':
            default:
                $query->orderBy('documents.created_at', $dir);
                break;
        }
    }

    /**
     * Lista documentos visibles para el usuario actual ordenados por fecha de creación descendente.
     */
    public function listOrderedByCreatedAtDesc(?string $processId = null): Collection
    {
        $query = Document::withoutGlobalScopes(['join_head_document_entity_version'])
            ->join('entity_versions as document_head_ev', 'document_head_ev.id', '=', 'documents.head_entity_version_id')
            ->select(['documents.*', 'owner_user.name as owner_name'])
            ->leftJoin('users as owner_user', function ($join) {
                $join->whereRaw(
                    'owner_user.id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id')
                );
            })
            ->withExists(['reviews as has_review_comments' => fn ($q) => $q->where('status', 'rejected')])
            ->with(['template', 'templateVersion'])
            ->orderByDesc('documents.created_at');

        if ($processId !== null) {
            $query->where('documents.process_id', $processId);
        }

        return $query->get();
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

        $query = DB::table('document_reviews as dr')
            ->join('documents as d', 'd.id', '=', 'dr.document_id')
            ->join('entity_versions as document_head_ev', 'document_head_ev.id', '=', 'd.head_entity_version_id')
            ->join('templates as t', 't.id', '=', 'd.template_id')
            ->join('entity_versions as template_head_ev', 'template_head_ev.id', '=', 't.head_entity_version_id')
            ->leftJoin('users as owner_user', function ($join) {
                $join->whereRaw(
                    'owner_user.id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id')
                );
            })
            ->leftJoinSub($minPendingByDocument, 'ps', function ($join) {
                $join->on('ps.document_id', '=', 'd.id');
            })
            ->where('dr.reviewer_id', $userId)
            ->where('dr.status', 'pending')
            ->whereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'status').' = ?', ['in_review'])
            ->where(function ($q) {
                $effectiveMode = DocumentHeadSnapshot::effectiveReviewModeExpression('document_head_ev', 'template_head_ev');
                $q->whereRaw("{$effectiveMode} = ?", ['parallel'])
                    ->orWhere(function ($q2) use ($effectiveMode) {
                        $q2->whereRaw("{$effectiveMode} = ?", ['sequential'])
                            ->whereColumn('dr.stage', 'ps.min_stage');
                    });
            });

        $rows = $query
            ->orderByRaw(
                'CASE WHEN '.DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'delivery_deadline').' IS NULL THEN 1 ELSE 0 END ASC'
            )
            ->orderByRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'delivery_deadline').' ASC')
            ->orderByDesc('d.updated_at')
            ->get([
                'd.id as document_id',
                DB::raw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'title').' as title'),
                DB::raw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id').' as owner_id'),
                DB::raw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'delivery_deadline').' as delivery_deadline'),
                DB::raw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'status').' as status'),
                'dr.id as review_id',
                'dr.stage',
                DB::raw(DocumentHeadSnapshot::effectiveReviewModeExpression('document_head_ev', 'template_head_ev').' as review_mode'),
                'owner_user.name as owner_name',
            ]);

        $today = Date::today();

        return $rows->map(function (object $row) use ($today): array {
            $deadlineIso = null;
            $daysRemaining = null;
            if ($row->delivery_deadline !== null) {
                $deadline = Date::parse((string) $row->delivery_deadline);
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
            ->join('entity_versions as document_head_ev', 'document_head_ev.id', '=', 'documents.head_entity_version_id')
            ->where('documents.id', $documentId)
            ->where(fn ($q) => $q
                ->whereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id').' = ?', [$userId])
                ->orWhereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'created_by').' = ?', [$userId])
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
     * Mayor número de versión según {@see EntityVersion} (fuente canónica).
     */
    public function maxDocumentVersionNumber(string $documentId): int
    {
        $max = EntityVersion::query()
            ->where('versionable_type', Document::class)
            ->where('versionable_id', $documentId)
            ->max('version_number');

        return $max !== null ? (int) $max : 0;
    }

    public function maxDocumentVersionHistoryNumber(string $documentId): int
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
        ?array $snapshotData,
        ?string $notes = null,
        ?string $entityVersionId = null,
    ): void {
        $attributes = [
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'version_number' => $versionNumber,
            'trigger_event' => $triggerEvent,
            'triggered_by' => $triggeredBy,
            'snapshot_data' => $snapshotData,
            'notes' => $notes,
            'is_immutable' => true,
            'created_at' => now(),
        ];

        if ($entityVersionId !== null) {
            $attributes['entity_version_id'] = $entityVersionId;
        }

        DocumentVersion::forceCreate($attributes);
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
     * Última versión de snapshot del documento por número de versión.
     */
    public function findLatestDocumentVersionOrFail(string $documentId): DocumentVersion
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->orderByDesc('version_number')
            ->firstOrFail();
    }

    public function findLatestPublishedDocumentVersion(string $documentId): ?DocumentVersion
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->where('trigger_event', 'published')
            ->orderByDesc('version_number')
            ->first();
    }

    public function findLegacyDocumentVersionsOrderedDesc(string $documentId): Collection
    {
        return DocumentVersion::query()
            ->where('document_id', $documentId)
            ->orderByDesc('version_number')
            ->get();
    }

    /**
     * Contexto académico de módulo para creación documental.
     *
     * @return array{module_id: string, study_id: string, study_type_id: ?string}|null
     */
    public function findModuleContext(string $moduleId): ?array
    {
        $row = DB::table('course_modules as cm')
            ->leftJoin('studies as s', 's.id', '=', 'cm.study_id')
            ->where('cm.id', $moduleId)
            ->select([
                'cm.id as module_id',
                'cm.study_id',
                's.study_type_id',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'module_id' => (string) $row->module_id,
            'study_id' => (string) $row->study_id,
            'study_type_id' => $row->study_type_id !== null ? (string) $row->study_type_id : null,
        ];
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

    public function findAssignedReviewerDocumentIds(array $documentIds, string $reviewerId): array
    {
        if ($documentIds === []) {
            return [];
        }

        return DocumentReview::query()
            ->whereIn('document_id', $documentIds)
            ->where('reviewer_id', $reviewerId)
            ->pluck('document_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function isReviewerAssignedToDocument(string $documentId, string $reviewerId): bool
    {
        return DocumentReview::query()
            ->where('document_id', $documentId)
            ->where('reviewer_id', $reviewerId)
            ->exists();
    }

    public function ownerIdsByTemplate(string $templateId, string $status = 'active'): array
    {
        $ownerExpr = DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id');
        $statusExpr = DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'status');

        $query = Document::query()
            ->withoutGlobalScope('user_access')
            ->where('documents.template_id', $templateId)
            ->whereRaw("{$ownerExpr} IS NOT NULL")
            ->whereRaw("{$ownerExpr} <> ''");

        if ($status === 'active') {
            $query->whereRaw("{$statusExpr} IN (?, ?)", ['draft', 'in_review']);
        } else {
            $query->whereRaw("{$statusExpr} = ?", [$status]);
        }

        return $query
            ->selectRaw("{$ownerExpr} as owner_id")
            ->pluck('owner_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Busca un documento por su ID con control de acceso (scope user_access),
     * o lanza ModelNotFoundException. Para usar en operaciones que necesitan autorización.
     */
    public function findByIdWithAccessControl(string $id): Document
    {
        return Document::query()
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Busca los bloques de un documento ordenados por sort_order, con solo columnas de contenido.
     * Para uso en exportación/renderizado.
     *
     * @return Collection<int, DocumentBlock>
     */
    public function findBlocksForExport(string $documentId): Collection
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->orderBy('sort_order')
            ->get(['content', 'sort_order']);
    }

    /**
     * Fetch document blocks as DTOs, ordered by sort_order.
     * Encapsulates model access; exposes only needed data as DTO.
     *
     * @return Collection<int, DocumentBlockPayloadDto>
     */
    public function findBlocksAsPayloadDtosForDocument(string $documentId): Collection
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->orderBy('sort_order')
            ->get()
            ->map(function (DocumentBlock $block) {
                return new DocumentBlockPayloadDto(
                    blockId: (string) $block->id,
                    templateBlockId: $block->template_block_id ? (string) $block->template_block_id : null,
                    content: $block->content,
                    isFilled: (bool) $block->is_filled,
                    sortOrder: (int) $block->sort_order,
                    lastEditedBy: $block->last_edited_by ? (string) $block->last_edited_by : null,
                    lockedBy: $block->locked_by ? (string) $block->locked_by : null,
                    lockedAt: $block->locked_at ? $block->locked_at->toIso8601String() : null,
                );
            });
    }

    /**
     * Carga los bloques y revisiones del documento para construcción de snapshot.
     *
     * @return array{
     *     document: Document,
     *     blocks: list<array{id: mixed, template_block_id: mixed, content: mixed, is_filled: bool, sort_order: int, last_edited_by: mixed, locked_by: mixed, locked_at: ?string}>,
     *     reviews: list<array{reviewer_id: string, stage: int|null, status: string}>
     * }
     */
    public function loadBlocksAndReviewsData(string $documentId): array
    {
        $document = $this->findOrFailForRefreshAfterMutation($documentId);
        $document->load([
            'blocks' => fn ($q) => $q->orderBy('sort_order'),
            'reviews' => fn ($q) => $q->orderBy('stage')->orderBy('created_at'),
        ]);

        $blocks = $document->blocks->map(static function ($b): array {
            return [
                'id' => $b->id,
                'template_block_id' => $b->template_block_id,
                'content' => $b->content,
                'is_filled' => (bool) $b->is_filled,
                'sort_order' => (int) $b->sort_order,
                'last_edited_by' => $b->last_edited_by,
                'locked_by' => $b->locked_by,
                'locked_at' => $b->locked_at?->toIso8601String(),
            ];
        })->values()->all();

        $reviews = $document->reviews->map(static function ($r): array {
            return [
                'reviewer_id' => (string) $r->reviewer_id,
                'stage' => $r->stage !== null ? (int) $r->stage : null,
                'status' => (string) ($r->status ?? 'pending'),
            ];
        })->values()->all();

        return ['document' => $document, 'blocks' => $blocks, 'reviews' => $reviews];
    }

    /**
     * Carga la relación headVersion en el modelo si no está cargada.
     */
    public function loadHeadVersion(Document $document): void
    {
        $document->loadMissing('headVersion');
    }

    /**
     * Carga la relación owner en el modelo si no está cargada.
     */
    public function loadOwner(Document $document): void
    {
        $document->loadMissing('owner');
    }

    /**
     * (Re)carga los bloques del documento ordenados por sort_order.
     * Fuerza la recarga (load, no loadMissing) para garantizar el orden.
     */
    public function loadOrderedBlocks(Document $document): void
    {
        $document->load(['blocks' => fn ($q) => $q->orderBy('sort_order')]);
    }

    /**
     * Carga la relación template en el modelo si no está cargada.
     */
    public function loadTemplate(Document $document): void
    {
        $document->loadMissing('template');
    }

    /**
     * Carga la relación templateVersion en el modelo si no está cargada.
     */
    public function loadTemplateVersion(Document $document): void
    {
        $document->loadMissing('templateVersion');
    }
}
