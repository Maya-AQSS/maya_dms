<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAcademicOverlapScope;
use App\Models\Concerns\HasCommentingStatus;
use App\Models\Concerns\HasEntityVersionHead;
use App\Observers\DocumentObserver;
use App\Policies\DocumentPolicy;
use App\Support\DocumentHeadSnapshot;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Ancla en proceso/plantilla; metadatos de título, ámbito, titularidad y estado del ciclo en la versión cabezal ({@see EntityVersion}, número 0).
 */
#[ObservedBy(DocumentObserver::class)]
class Document extends Model
{
    use HasAcademicOverlapScope, HasCommentingStatus, HasEntityVersionHead, HasUuids, SoftDeletes;

    /**
     * Visibilidad efectiva (SQL):
     * - Creador o titular (snapshot cabezal).
     * - Compartidos en document_shares.
     * - Revisor asignado mientras el documento esté en ciclo activo (status = 'in_review'
     *   y existe fila en document_reviews para ese usuario, en cualquier estado).
     * - Documentos publicados visibles en el mismo ámbito académico del usuario en BD.
     *
     * Requiere JOIN `entity_versions` alias {@code document_head_ev} vía {@see static::scopeJoinHeadDocumentEntityVersion}.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('join_head_document_entity_version', function (Builder $builder) {
            static::scopeJoinHeadDocumentEntityVersion($builder);
        });

        static::addGlobalScope('user_access', function (Builder $builder) {
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $user = auth()->user();
            if ($user instanceof JwtUser && $user->canReadAll()) {
                // Admin de SOLO LECTURA: ve todas las filas (se conserva el join de cabezal).
                // La escritura nunca pasa por aquí: las policies usan applyUserAccessFilter()
                // con el scope desactivado (ver DocumentPolicy::viewScoped()).
                return;
            }

            $userId = (string) ($user?->getAuthIdentifier() ?? '');
            if ($userId === '') {
                $builder->whereRaw('1 = 0');

                return;
            }

            self::applyUserAccessFilter($builder, $userId);
        });

        static::registerEntityVersionHeadHook();
    }

    /**
     * Filtro real de visibilidad por usuario (owner/creador/share/revisor/publicado).
     *
     * Centralizado para que {@see DocumentPolicy::viewScoped()} pueda
     * reaplicarlo con el scope global `user_access` desactivado, garantizando que el
     * bypass de admin-lectura JAMÁS se aplique en una decisión de escritura.
     */
    public static function applyUserAccessFilter(Builder $builder, string $userId): void
    {
        $builder->where(function (Builder $outer) use ($userId) {
            $outer->where('document_head_ev.snapshot_data->document->owner_id', $userId)
                ->orWhere(function (Builder $author) use ($userId) {
                    // El autor (created_by) conserva acceso solo mientras no exista un
                    // titular operativo distinto. Tras ceder la titularidad (owner_id
                    // pasa a otro usuario) deja de tener acceso al documento.
                    $author->where('document_head_ev.snapshot_data->document->created_by', $userId)
                        ->where('document_head_ev.snapshot_data->document->owner_id', '');
                })
                ->orWhereExists(function ($subQuery) use ($userId) {
                    $subQuery->select(DB::raw(1))
                        ->from('document_shares')
                        ->whereColumn('document_shares.document_id', 'documents.id')
                        ->where('user_id', $userId);
                })
                ->orWhere(function ($q) use ($userId) {
                    // Any assigned reviewer (any status) can see the document while in_review.
                    // Stage/status constraints are enforced at action level (approve/reject), not visibility.
                    $q->where('document_head_ev.snapshot_data->document->status', 'in_review')
                        ->whereExists(function ($subQuery) use ($userId) {
                            $subQuery->select(DB::raw(1))
                                ->from('document_reviews as dr_scope')
                                ->whereColumn('dr_scope.document_id', 'documents.id')
                                ->where('dr_scope.reviewer_id', $userId);
                        });
                })
                ->orWhere(function (Builder $pub) use ($userId) {
                    // Catálogo publicado: visible aunque el head esté en draft/in_review.
                    // El contexto se evalúa sobre el snapshot publicado (pub_snap), no
                    // sobre el head en curso, para no ocultar la versión publicada cuando
                    // alguien está editando una nueva versión.
                    $pub->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('entity_versions as pub_snap')
                            ->whereColumn('pub_snap.versionable_id', 'documents.id')
                            ->where('pub_snap.versionable_type', self::class)
                            ->where('pub_snap.version_number', '>', 0)
                            ->where('pub_snap.status', 'published')
                            ->where(function ($ctx) use ($userId) {
                                self::applyAcademicOverlapOnDocumentSnapshotAlias($ctx, $userId, 'pub_snap');
                            });
                    });
                });
        });
    }

    protected static function delegatedAttributes(): array
    {
        return DocumentHeadSnapshot::DELEGATED_ATTRIBUTES;
    }

    protected static function buildHeadSnapshot(array $row, string $modelId, string $processId): array
    {
        $templateId = (string) ($row['template_id'] ?? '');

        return DocumentHeadSnapshot::buildPayloadFromLegacyRow($row, $modelId, $processId, $templateId);
    }

    protected static function snapshotCreatedByKey(): array
    {
        return ['document', 'created_by'];
    }

    protected static function validateBeforeHeadSnapshot(self $model): void
    {
        if (empty($model->template_id)) {
            throw new \RuntimeException('Documento sin template_id.');
        }
    }

    protected static function headSnapshotJsonFieldExpression(string $alias, string $field): string
    {
        return DocumentHeadSnapshot::jsonDocumentFieldExpression($alias, $field);
    }

    /**
     * Inner join idempotente al cabezal de versión (una fila por documento).
     */
    public static function scopeJoinHeadDocumentEntityVersion(Builder $builder): void
    {
        $joins = $builder->getQuery()->joins ?? [];
        foreach ($joins as $join) {
            if (isset($join->table) && $join->table === 'entity_versions as document_head_ev') {
                return;
            }
        }

        $baseQuery = $builder->getQuery();
        $existingColumns = $baseQuery->columns ?? [];
        $hasWildcard = collect($existingColumns)->contains(
            fn ($col) => ! $col instanceof Expression
                && ($col === $builder->getModel()->getTable().'.*' || $col === '*'),
        );
        if (! $hasWildcard) {
            $builder->addSelect($builder->getModel()->getTable().'.*');
        }

        $builder->join('entity_versions as document_head_ev', 'document_head_ev.id', '=', 'documents.head_entity_version_id');

        // Precompute aggregate columns to avoid N+1 accessor queries on list endpoints.
        $type = addslashes(self::class);
        $statusExpr = DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'status');
        $builder->addSelect([
            DB::raw("GREATEST(1, COALESCE((
                SELECT MAX(ev.version_number)
                FROM entity_versions ev
                WHERE ev.versionable_type = '{$type}'
                  AND ev.versionable_id = documents.id
                  AND ev.status = 'published'
            ), 0)) AS current_version"),
            DB::raw("CASE WHEN {$statusExpr} = 'draft' THEN NULL
                ELSE COALESCE(
                    (SELECT MIN(dr.created_at) FROM document_reviews dr WHERE dr.document_id = documents.id),
                    (SELECT ev.published_at FROM entity_versions ev
                     WHERE ev.versionable_type = '{$type}' AND ev.versionable_id = documents.id
                       AND ev.status = 'published'
                     ORDER BY ev.version_number ASC LIMIT 1)
                )
            END AS submitted_at"),
            DB::raw("CASE WHEN {$statusExpr} != 'published' THEN NULL
                ELSE (SELECT ev.published_at FROM entity_versions ev
                      WHERE ev.versionable_type = '{$type}' AND ev.versionable_id = documents.id
                        AND ev.status = 'published'
                      ORDER BY ev.version_number DESC LIMIT 1)
            END AS published_at"),
        ]);
    }

    /**
     * Condiciones OR sobre columnas del documento alineadas con contexto académico en BD.
     */
    public static function applyAcademicOverlapOnDocumentsTable(Builder|QueryBuilder $query, string $userId): void
    {
        self::applyAcademicOverlapForTableAlias($query, $userId, 'documents');
    }

    /**
     * Solape académico usando el JSON snapshot de una fila publicada
     * (alias de entity_versions) en lugar del head actual del documento.
     */
    private static function applyAcademicOverlapOnDocumentSnapshotAlias(
        Builder|QueryBuilder $query,
        string $userId,
        string $snapshotAlias
    ): void {
        $s = rtrim($snapshotAlias, '.');

        $query->where(function ($w) use ($userId, $s) {
            $w->whereExists(function ($sub) use ($userId, $s) {
                $sub->select(DB::raw(1))
                    ->from('user_study_types')
                    ->where('user_study_types.user_id', $userId)
                    ->whereRaw(
                        'user_study_types.study_type_id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression($s, 'study_type_id')
                    );
            })->orWhereExists(function ($sub) use ($userId, $s) {
                $sub->select(DB::raw(1))
                    ->from('user_studies')
                    ->where('user_studies.user_id', $userId)
                    ->whereRaw(
                        'user_studies.study_id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression($s, 'study_id')
                    );
            })->orWhereExists(function ($sub) use ($userId, $s) {
                $sub->select(DB::raw(1))
                    ->from('user_course_modules')
                    ->where('user_course_modules.user_id', $userId)
                    ->whereRaw(
                        'user_course_modules.module_id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression($s, 'module_id')
                    );
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'template_version_id',
        'process_id',
        'head_entity_version_id',
    ];

    public function getAttribute($key): mixed
    {
        if (in_array($key, DocumentHeadSnapshot::DELEGATED_ATTRIBUTES, true)) {
            $this->loadMissing('headVersion');
            $raw = data_get($this->headVersion?->snapshot_data, DocumentHeadSnapshot::JSON_DOCUMENT_KEY.'.'.$key);

            return $this->castHeadDelegatedAttribute((string) $key, $raw);
        }

        return parent::getAttribute($key);
    }

    protected function castHeadDelegatedAttribute(string $key, mixed $raw): mixed
    {
        return match ($key) {
            'delivery_deadline' => $raw !== null && $raw !== ''
                ? Date::parse((string) $raw)
                : null,
            default => $raw,
        };
    }

    public function currentVersion(): int
    {
        return $this->current_version;
    }

    /**
     * Número de versión publicada canónica ({@see EntityVersion}), con convención «1» antes de la primera publicación.
     *
     * @param  mixed  $value  Ignorado (derivado).
     */
    public function getCurrentVersionAttribute(mixed $value): int
    {
        // Fast-path: the global scope `join_head_document_entity_version` adds
        // `current_version` as a SQL GREATEST(1, COALESCE(max(published ev),0))
        // column whenever the model is loaded via a query that includes the scope
        // (e.g. DocumentRepository::findOrFail). Reading it here avoids the extra
        // Eloquent subquery below. The fallback executes only when the model was
        // loaded without the global scope (e.g. findWithBlocksAndThemeOrFail which
        // strips `user_access` but does NOT add the aggregate selects).
        if (array_key_exists('current_version', $this->attributes)) {
            return max(1, (int) $this->attributes['current_version']);
        }

        $max = EntityVersion::query()
            ->where('versionable_type', self::class)
            ->where('versionable_id', $this->getKey())
            ->where('status', 'published')
            ->max('version_number');
        $n = $max !== null ? (int) $max : 0;

        return $n > 0 ? $n : 1;
    }

    /**
     * Inicio del ciclo de revisión (mínimo {@code created_at} en {@code document_reviews}) o, sin revisión previa y
     * estado publicado, el {@see EntityVersion::published_at} de la primera publicación.
     *
     * @param  mixed  $value  Ignorado.
     */
    public function getSubmittedAtAttribute(mixed $value): ?Carbon
    {
        if (array_key_exists('submitted_at', $this->attributes)) {
            return $this->attributes['submitted_at'] !== null ? Date::parse($this->attributes['submitted_at']) : null;
        }

        if ($this->status === 'draft') {
            return null;
        }

        $reviewFirst = DB::table('document_reviews')
            ->where('document_id', $this->getKey())
            ->min('created_at');

        if ($reviewFirst !== null) {
            return Date::parse($reviewFirst);
        }

        if ($this->status === 'published') {
            $ev = EntityVersion::query()
                ->where('versionable_type', self::class)
                ->where('versionable_id', $this->getKey())
                ->where('status', 'published')
                ->orderBy('version_number')
                ->first();

            return $ev?->published_at;
        }

        return null;
    }

    /**
     * Fecha de la última publicación ({@see EntityVersion::published_at}).
     *
     * @param  mixed  $value  Ignorado.
     */
    public function getPublishedAtAttribute(mixed $value): ?Carbon
    {
        if (array_key_exists('published_at', $this->attributes)) {
            return $this->attributes['published_at'] !== null ? Date::parse($this->attributes['published_at']) : null;
        }

        if ($this->status !== 'published') {
            return null;
        }

        $ev = EntityVersion::query()
            ->where('versionable_type', self::class)
            ->where('versionable_id', $this->getKey())
            ->where('status', 'published')
            ->orderByDesc('version_number')
            ->first();

        return $ev?->published_at;
    }

    protected function casts(): array
    {
        return [];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class)->withoutGlobalScopes(['user_access']);
    }

    /**
     * Publicación de plantilla usada al crear el documento: FK a {@see EntityVersion}.
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'template_version_id');
    }

    public function headVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'head_entity_version_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(DocumentBlock::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function entityVersions(): MorphMany
    {
        return $this->morphMany(EntityVersion::class, 'versionable')->orderBy('version_number');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function userFavoriteDocuments(): HasMany
    {
        return $this->hasMany(UserFavoriteDocument::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
