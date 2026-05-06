<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected static function booted(): void
    {
    /**
     * Visibilidad (SQL):
     * - Creador o titular.
     * - Compartidos en document_shares.
     * - Revisor asignado mientras el documento esté en ciclo activo (status = 'in_review'
     *   y existe fila en document_reviews para ese usuario, en cualquier estado).
     * - Documentos publicados visibles en el mismo ámbito académico del usuario en BD
     *   (user_study_types / user_studies / user_course_modules).
     */
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
                $outer->where('documents.created_by', $userId)
                    ->orWhere('documents.owner_id', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('document_shares')
                            ->whereColumn('document_shares.document_id', 'documents.id')
                            ->where('user_id', $userId);
                    })
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('documents.status', 'in_review')
                            ->whereExists(function ($subQuery) use ($userId) {
                                $subQuery->select(DB::raw(1))
                                    ->from('document_reviews')
                                    ->whereColumn('document_reviews.document_id', 'documents.id')
                                    ->where('document_reviews.reviewer_id', $userId);
                            });
                    })
                    ->orWhere(function (Builder $pub) use ($userId) {
                        $pub->where('documents.status', 'published')
                            ->where(function ($inner) use ($userId) {
                                self::applyAcademicOverlapOnDocumentsTable($inner, $userId);
                            });
                    });
            });
        });
    }

    /**
     * Condiciones OR sobre columnas del documento alineadas con contexto académico en BD.
     * Usable en subconsultas correlacionadas donde el FROM externo es `documents`.
     *
     * @param  Builder|QueryBuilder  $query
     */
    public static function applyAcademicOverlapOnDocumentsTable(Builder|QueryBuilder $query, string $userId): void
    {
        self::applyAcademicOverlapForTableAlias($query, $userId, 'documents');
    }

    /**
     * Misma regla que {@see applyAcademicOverlapOnDocumentsTable} con prefijo de tabla personalizado (p. ej. `d` en JOIN).
     *
     * @param  Builder|QueryBuilder  $query
     */
    public static function applyAcademicOverlapForTableAlias(Builder|QueryBuilder $query, string $userId, string $alias): void
    {
        $t = rtrim($alias, '.').'.';
        $query->where(function ($w) use ($userId, $t) {
            $w->whereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('user_study_types')
                    ->where('user_study_types.user_id', $userId)
                    ->whereColumn('user_study_types.study_type_id', $t.'study_type_id');
            })->orWhereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('user_studies')
                    ->where('user_studies.user_id', $userId)
                    ->whereColumn('user_studies.study_id', $t.'study_id');
            })->orWhereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('user_course_modules')
                    ->where('user_course_modules.user_id', $userId)
                    ->whereColumn('user_course_modules.module_id', $t.'module_id');
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'template_version_id',
        'process_id',
        'title',
        'study_type_id',
        'study_id',
        'module_id',
        'delivery_deadline',
        'created_by',
        'owner_id',
        'status',
    ];

    /**
     * Número de versión publicada canónica ({@see EntityVersion}), con convención «1» antes de la primera publicación.
     *
     * @param  mixed  $value  Ignorado (la columna ya no existe).
     */
    public function getCurrentVersionAttribute(mixed $value): int
    {
        $max = EntityVersion::query()
            ->where('versionable_type', self::class)
            ->where('versionable_id', $this->getKey())
            ->where('status', 'published')
            ->max('version_number');
        $n = $max !== null ? (int) $max : 0;

        return $n > 0 ? $n : 1;
    }

    /**
     * Inicio del ciclo de revisión actual o, si publicó sin revisores, el mismo instante de publicación (auto-publicación).
     *
     * @param  mixed  $value  Ignorado (la columna ya no existe).
     */
    public function getSubmittedAtAttribute(mixed $value): ?Carbon
    {
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
     * Solo si el documento está publicado en el ciclo actual; la historia permanece en {@see EntityVersion}.
     *
     * @param  mixed  $value  Ignorado (la columna ya no existe).
     */
    public function getPublishedAtAttribute(mixed $value): ?Carbon
    {
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
        return [
            'delivery_deadline' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        // Sin catálogo: quien puede ver el documento (p. ej. revisor) debe resolver la plantilla anclada.
        return $this->belongsTo(Template::class)->withoutGlobalScopes(['user_access']);
    }

    /**
     * Publicación de plantilla usada al crear el documento: FK a {@see EntityVersion} (snapshot canónico).
     *
     * Nombre de columna histórico: {@code template_version_id}.
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'template_version_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
