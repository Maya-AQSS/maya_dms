<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCommentingStatus;
use App\Support\DocumentHeadSnapshot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ancla en proceso/plantilla; metadatos de título, ámbito, titularidad y estado del ciclo en la versión cabezal ({@see EntityVersion}, número 0).
 */
class Document extends Model
{
    use HasCommentingStatus, HasUuids, SoftDeletes;

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

            $userId = (string) (auth()->user()?->getAuthIdentifier() ?? '');
            if ($userId === '') {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where(function (Builder $outer) use ($userId) {
                $outer->where('document_head_ev.snapshot_data->document->created_by', $userId)
                    ->orWhere('document_head_ev.snapshot_data->document->owner_id', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('document_shares')
                            ->whereColumn('document_shares.document_id', 'documents.id')
                            ->where('user_id', $userId);
                    })
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('document_head_ev.snapshot_data->document->status', 'in_review')
                            ->whereExists(function ($subQuery) use ($userId) {
                                $subQuery->select(DB::raw(1))
                                    ->from('document_reviews')
                                    ->whereColumn('document_reviews.document_id', 'documents.id')
                                    ->where('document_reviews.reviewer_id', $userId);
                            });
                    })
                    ->orWhere(function (Builder $pub) use ($userId) {
                        $pub->where('document_head_ev.snapshot_data->document->status', 'published')
                            ->where(function ($inner) use ($userId) {
                                self::applyAcademicOverlapOnDocumentsTable($inner, $userId);
                            });
                    });
            });
        });

        static::creating(function (Document $document): void {
            if ($document->head_entity_version_id !== null) {
                foreach (DocumentHeadSnapshot::DELEGATED_ATTRIBUTES as $attr) {
                    if (array_key_exists($attr, $document->getAttributes())) {
                        $document->offsetUnset($attr);
                    }
                }

                return;
            }

            $attrs = $document->getAttributes();
            $hasDelegated = false;
            foreach (DocumentHeadSnapshot::DELEGATED_ATTRIBUTES as $attr) {
                if (array_key_exists($attr, $attrs)) {
                    $hasDelegated = true;
                    break;
                }
            }

            if (! $hasDelegated) {
                return;
            }

            if (empty($document->process_id)) {
                $document->process_id = Process::query()->value('id') ?? '00000000-0000-0000-0000-000000000001';
            }
            if (empty($document->template_id)) {
                throw new \RuntimeException('Documento sin template_id.');
            }

            $row = $document->getAttributes();
            $documentId = (string) $document->getKey();
            $snapshot = DocumentHeadSnapshot::buildPayloadFromLegacyRow(
                $row,
                $documentId,
                (string) $document->process_id,
                (string) $document->template_id,
            );

            $headId = (string) Str::uuid();
            $now = now();
            $status = (string) ($row['status'] ?? 'draft');
            if ($status === 'published') {
                $status = 'draft';
            }
            $createdBy = (string) ($row['created_by'] ?? $snapshot['document']['created_by'] ?? '');

            DB::table('entity_versions')->insert([
                'id' => $headId,
                'versionable_type' => self::class,
                'versionable_id' => $documentId,
                'version_number' => 0,
                'base_version_id' => null,
                'change_set' => null,
                'status' => $status,
                'created_by' => $createdBy,
                'published_by' => null,
                'published_at' => null,
                'changelog' => null,
                'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $document->setAttribute('head_entity_version_id', $headId);

            foreach (DocumentHeadSnapshot::DELEGATED_ATTRIBUTES as $attr) {
                if (array_key_exists($attr, $document->getAttributes())) {
                    $document->offsetUnset($attr);
                }
            }
        });
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
     * Misma regla con prefijo de tabla personalizado (p. ej. `d` en JOIN).
     */
    public static function applyAcademicOverlapForTableAlias(Builder|QueryBuilder $query, string $userId, string $alias): void
    {
        $t = rtrim($alias, '.');
        $query->where(function ($w) use ($userId, $t) {
            $w->whereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('entity_versions')
                    ->whereColumn('entity_versions.versionable_id', $t.'.id')
                    ->where('entity_versions.versionable_type', self::class)
                    ->where('entity_versions.version_number', 0)
                    ->whereExists(function ($inner) use ($userId) {
                        $inner->select(DB::raw(1))
                            ->from('user_study_types')
                            ->where('user_study_types.user_id', $userId)
                            ->whereRaw(
                                'user_study_types.study_type_id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression('entity_versions', 'study_type_id')
                            );
                    });
            })->orWhereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('entity_versions')
                    ->whereColumn('entity_versions.versionable_id', $t.'.id')
                    ->where('entity_versions.versionable_type', self::class)
                    ->where('entity_versions.version_number', 0)
                    ->whereExists(function ($inner) use ($userId) {
                        $inner->select(DB::raw(1))
                            ->from('user_studies')
                            ->where('user_studies.user_id', $userId)
                            ->whereRaw(
                                'user_studies.study_id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression('entity_versions', 'study_id')
                            );
                    });
            })->orWhereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('entity_versions')
                    ->whereColumn('entity_versions.versionable_id', $t.'.id')
                    ->where('entity_versions.versionable_type', self::class)
                    ->where('entity_versions.version_number', 0)
                    ->whereExists(function ($inner) use ($userId) {
                        $inner->select(DB::raw(1))
                            ->from('user_course_modules')
                            ->where('user_course_modules.user_id', $userId)
                            ->whereRaw(
                                'user_course_modules.module_id = '.DocumentHeadSnapshot::jsonDocumentFieldExpression('entity_versions', 'module_id')
                            );
                    });
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
                ? Carbon::parse((string) $raw)
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
        if (array_key_exists('current_version', $this->attributes)) {
            return (int) $this->attributes['current_version'];
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
            return $this->attributes['submitted_at'] !== null ? Carbon::parse($this->attributes['submitted_at']) : null;
        }

        if ($this->status === 'draft') {
            return null;
        }

        $reviewFirst = DB::table('document_reviews')
            ->where('document_id', $this->getKey())
            ->min('created_at');

        if ($reviewFirst !== null) {
            return Carbon::parse($reviewFirst);
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
            return $this->attributes['published_at'] !== null ? Carbon::parse($this->attributes['published_at']) : null;
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
