<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Concerns\HasAcademicOverlapScope;
use App\Models\Concerns\HasCommentingStatus;
use App\Models\Concerns\HasEntityVersionHead;
use App\Models\Concerns\HasPresentationAttributes;
use App\Observers\TemplateObserver;
use App\Policies\TemplatePolicy;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Ancla en proceso; metadatos de nombre, visibilidad, revisión, etc. en la versión cabezal ({@see EntityVersion}, número 0).
 */
#[ObservedBy(TemplateObserver::class)]
class Template extends Model
{
    use HasAcademicOverlapScope, HasCommentingStatus, HasEntityVersionHead, HasPresentationAttributes, HasUuids, SoftDeletes;

    /**
     * Visibilidad efectiva (solo SQL; la lectura API exige además `templates.read` en {@see TemplatePolicy}):
     * - Creador o revisor asignado en `template_reviewers` (acceso a esa plantilla concreta; editar/comentar se gobiernan aparte).
     * - Plantillas compartidas según nivel (global, tipo de estudio, estudio, módulo, equipo)
     *   usando contexto académico resuelto en BD y membresía en team_members.
     *
     * Requiere JOIN `entity_versions` alias {@code template_head_ev} vía {@see static::scopeJoinHeadEntityVersion}.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('join_head_entity_version', function (Builder $builder) {
            static::scopeJoinHeadEntityVersion($builder);
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
                // con el scope desactivado (ver TemplatePolicy::viewScoped()).
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
     * Filtro real de visibilidad por usuario (creador/legacy/revisor/creador-original/compartida).
     *
     * Centralizado para que {@see TemplatePolicy::viewScoped()} pueda
     * reaplicarlo con el scope global `user_access` desactivado, garantizando que el
     * bypass de admin-lectura JAMÁS se aplique en una decisión de escritura.
     */
    public static function applyUserAccessFilter(Builder $builder, string $userId): void
    {
        $builder->where(function (Builder $outer) use ($userId) {
            $headCreatorExpr = TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'created_by');
            $outer->whereRaw($headCreatorExpr.' = ?', [$userId])
                ->orWhere(function (Builder $legacyOwner) use ($userId, $headCreatorExpr) {
                    $legacyOwner
                        ->where(function (Builder $emptyHeadCreator) use ($headCreatorExpr) {
                            $emptyHeadCreator
                                ->whereRaw($headCreatorExpr.' IS NULL')
                                ->orWhereRaw($headCreatorExpr." = ''");
                        })
                        ->where('template_head_ev.created_by', $userId);
                })
                ->orWhere(function (Builder $reviewScope) use ($userId) {
                    $reviewScope->where('template_head_ev.snapshot_data->template->status', 'in_review')
                        ->whereExists(function ($subQuery) use ($userId) {
                            $subQuery->select(DB::raw(1))
                                ->from('template_reviewers')
                                ->whereColumn('template_reviewers.template_id', 'templates.id')
                                ->where('template_reviewers.user_id', $userId);
                        });
                })
                ->orWhere(function (Builder $prevCreator) use ($userId) {
                    // Creador original que cedió la plantilla: puede seguir viendo las
                    // versiones publicadas si aparece como created_by en algún snapshot inmutable.
                    $prevCreator->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('entity_versions as prev_pub')
                            ->whereColumn('prev_pub.versionable_id', 'templates.id')
                            ->where('prev_pub.versionable_type', self::class)
                            ->where('prev_pub.version_number', '>', 0)
                            ->where('prev_pub.is_snapshot_immutable', true)
                            ->whereRaw(
                                TemplateHeadSnapshot::jsonTemplateFieldExpression('prev_pub', 'created_by').' = ?',
                                [$userId]
                            );
                    });
                });

            $outer->orWhere(fn (Builder $shared) => self::scopeSharedTemplatesForTeacher($shared, $userId));
        });
    }

    protected static function delegatedAttributes(): array
    {
        return TemplateHeadSnapshot::DELEGATED_ATTRIBUTES;
    }

    protected static function buildHeadSnapshot(array $row, string $modelId, string $processId): array
    {
        return TemplateHeadSnapshot::buildPayloadFromLegacyRow($row, $modelId, $processId);
    }

    protected static function snapshotCreatedByKey(): array
    {
        return ['template', 'created_by'];
    }

    protected static function headSnapshotJsonFieldExpression(string $alias, string $field): string
    {
        return TemplateHeadSnapshot::jsonTemplateFieldExpression($alias, $field);
    }

    /**
     * Inner join idempotente al cabezal de versión (una fila por plantilla).
     */
    public static function scopeJoinHeadEntityVersion(Builder $builder): void
    {
        $joins = $builder->getQuery()->joins ?? [];
        foreach ($joins as $join) {
            if (isset($join->table) && $join->table === 'entity_versions as template_head_ev') {
                return;
            }
        }

        $baseQuery = $builder->getQuery();
        if ($baseQuery->columns === null) {
            $builder->select($builder->getModel()->getTable().'.*');
        }

        $builder->join('entity_versions as template_head_ev', 'template_head_ev.id', '=', 'templates.head_entity_version_id');
    }

    /**
     * Docente: niveles compartidos (no incluye plantillas personales ajenas).
     */
    private static function scopeSharedTemplatesForTeacher(Builder $shared, string $userId): void
    {
        // Plantillas con al menos un snapshot publicado son visibles para no creadores/no revisores.
        // Aunque el cabezal esté en borrador (nueva versión en curso), el usuario ve la última
        // versión publicada (overlay en TemplateController::show; el listado muestra HEAD con
        // status='draft' que el frontend interpreta como "sólo lectura publicada").
        $shared->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('entity_versions as pub_snap')
                ->whereColumn('pub_snap.versionable_id', 'templates.id')
                ->where('pub_snap.versionable_type', self::class)
                ->where('pub_snap.version_number', '>', 0)
                ->where('pub_snap.status', 'published');
        });

        $shared->where(function (Builder $docente) use ($userId) {
            $docente->where('template_head_ev.snapshot_data->template->visibility_level', TemplateVisibilityLevel::Global->value);

            $docente->orWhere(function (Builder $st) use ($userId) {
                $st->where('template_head_ev.snapshot_data->template->visibility_level', TemplateVisibilityLevel::StudyType->value)
                    ->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('user_study_types')
                            ->where('user_study_types.user_id', $userId)
                            ->whereRaw(
                                'user_study_types.study_type_id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'study_type_id')
                            );
                    });
            });

            $docente->orWhere(function (Builder $s) use ($userId) {
                $s->where('template_head_ev.snapshot_data->template->visibility_level', TemplateVisibilityLevel::Study->value)
                    ->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('user_studies')
                            ->where('user_studies.user_id', $userId)
                            ->whereRaw(
                                'user_studies.study_id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'study_id')
                            );
                    });
            });

            $docente->orWhere(function (Builder $m) use ($userId) {
                $m->where('template_head_ev.snapshot_data->template->visibility_level', TemplateVisibilityLevel::Module->value)
                    ->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('user_course_modules')
                            ->where('user_course_modules.user_id', $userId)
                            ->whereRaw(
                                'user_course_modules.module_id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'module_id')
                            );
                    });
            });

            $docente->orWhere(function (Builder $gr) use ($userId) {
                $gr->where('template_head_ev.snapshot_data->template->visibility_level', TemplateVisibilityLevel::Team->value)
                    ->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('team_members')
                            ->whereRaw(
                                'team_members.team_id = '.TemplateHeadSnapshot::jsonTemplateFieldUuidExpression('template_head_ev', 'team_id')
                            )
                            ->where('team_members.user_id', $userId);
                    });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'process_id',
        'head_entity_version_id',
        'theme_id',
    ];

    public function getAttribute($key): mixed
    {
        if (in_array($key, TemplateHeadSnapshot::DELEGATED_ATTRIBUTES, true)) {
            $this->loadMissing('headVersion');
            $raw = data_get($this->headVersion?->snapshot_data, TemplateHeadSnapshot::JSON_TEMPLATE_KEY.'.'.$key);
            if ($key === 'created_by' && ($raw === null || $raw === '')) {
                $raw = $this->headVersion?->created_by;
                if ($raw === null || $raw === '') {
                    $raw = parent::getAttribute($key);
                }
            }

            return $this->castHeadDelegatedAttribute((string) $key, $raw);
        }

        return parent::getAttribute($key);
    }

    protected function castHeadDelegatedAttribute(string $key, mixed $raw): mixed
    {
        return match ($key) {
            'visibility_level' => $raw !== null && $raw !== ''
                ? TemplateVisibilityLevel::from((string) $raw)
                : TemplateVisibilityLevel::Personal,
            'delivery_deadline' => $raw !== null && $raw !== ''
                ? Date::parse((string) $raw)
                : null,
            'review_stages' => (int) ($raw ?? 0),
            default => $raw,
        };
    }

    public function currentVersion(): int
    {
        return $this->version;
    }

    /**
     * Número de la última publicación en {@see EntityVersion} (changelog y autores de esa fila en la misma tabla).
     *
     * @param  mixed  $value  Ignorado (la columna ya no existe).
     */
    public function getVersionAttribute(mixed $value): int
    {
        $max = EntityVersion::query()
            ->where('versionable_type', self::class)
            ->where('versionable_id', $this->getKey())
            ->where('status', 'published')
            ->max('version_number');

        return $max !== null ? (int) $max : 1;
    }

    protected function casts(): array
    {
        return [];
    }

    public function headVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'head_entity_version_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /**
     * Identidad visual aplicada a los documentos que se generen desde esta
     * plantilla. Opcional: si null, se usa el theme por defecto del sistema.
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TemplateBlock::class);
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(TemplateReviewer::class);
    }

    public function documentReviewers(): HasMany
    {
        return $this->hasMany(TemplateDocumentReviewer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function userFavoriteTemplates(): HasMany
    {
        return $this->hasMany(UserFavoriteTemplate::class);
    }

    public function publishedVersions(): MorphMany
    {
        return $this->morphMany(EntityVersion::class, 'versionable')
            ->where('status', 'published')
            ->orderBy('version_number');
    }

    public function entityVersions(): MorphMany
    {
        return $this->morphMany(EntityVersion::class, 'versionable')->orderBy('version_number');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
