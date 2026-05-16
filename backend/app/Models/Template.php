<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Concerns\HasCommentingStatus;
use App\Support\TemplateHeadSnapshot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Ancla en proceso; metadatos de nombre, visibilidad, revisión, etc. en la versión cabezal ({@see EntityVersion}, número 0).
 */
class Template extends Model
{
    use HasUuids, SoftDeletes, HasCommentingStatus;

    /**
     * Visibilidad efectiva (solo SQL; la lectura API exige además `templates.read` en {@see \App\Policies\TemplatePolicy}):
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

            $userId = (string) (auth()->user()?->getAuthIdentifier() ?? '');
            if ($userId === '') {
                $builder->whereRaw('1 = 0');

                return;
            }

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
                    });

                $outer->orWhere(fn (Builder $shared) => self::scopeSharedTemplatesForTeacher($shared, $userId));
            });
        });

        static::creating(function (Template $template): void {
            if ($template->head_entity_version_id !== null) {
                foreach (TemplateHeadSnapshot::DELEGATED_ATTRIBUTES as $attr) {
                    if (array_key_exists($attr, $template->getAttributes())) {
                        $template->offsetUnset($attr);
                    }
                }

                return;
            }

            $attrs = $template->getAttributes();
            $hasDelegated = false;
            foreach (TemplateHeadSnapshot::DELEGATED_ATTRIBUTES as $attr) {
                if (array_key_exists($attr, $attrs)) {
                    $hasDelegated = true;
                    break;
                }
            }

            if (! $hasDelegated) {
                return;
            }

            if (empty($template->process_id)) {
                $defaultProcess = '00000000-0000-0000-0000-000000000001';
                $template->process_id = Process::query()->value('id') ?? $defaultProcess;
            }

            $row = $template->getAttributes();
            $templateId = (string) $template->getKey();
            $processId = (string) $template->process_id;
            $snapshot = TemplateHeadSnapshot::buildPayloadFromLegacyRow($row, $templateId, $processId);

            $headId = (string) Str::uuid();
            $now = now();
            $status = (string) ($row['status'] ?? 'draft');
            if ($status === 'published') {
                $status = 'draft';
            }
            $createdBy = (string) ($row['created_by'] ?? $snapshot['template']['created_by'] ?? '');

            DB::table('entity_versions')->insert([
                'id' => $headId,
                'versionable_type' => self::class,
                'versionable_id' => $templateId,
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

            $template->setAttribute('head_entity_version_id', $headId);

            foreach (TemplateHeadSnapshot::DELEGATED_ATTRIBUTES as $attr) {
                if (array_key_exists($attr, $template->getAttributes())) {
                    $template->offsetUnset($attr);
                }
            }
        });
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

    /**
     * Solapa académico en BD para alias de plantilla (p.ej. templates / t).
     *
     * @param  Builder|QueryBuilder  $query
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
                                'user_study_types.study_type_id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('entity_versions', 'study_type_id')
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
                                'user_studies.study_id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('entity_versions', 'study_id')
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
                                'user_course_modules.module_id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('entity_versions', 'module_id')
                            );
                    });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'process_id',
        'head_entity_version_id',
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
                ? Carbon::parse((string) $raw)
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
