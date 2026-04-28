<?php

namespace App\Models;

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Template extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * Visibilidad efectiva (solo SQL; la lectura API exige además `templates.read` en {@see \App\Policies\TemplatePolicy}):
     * - Creador o revisor asignado en `template_reviewers` (acceso a esa plantilla concreta; editar/comentar se gobiernan aparte).
     * - Plantillas compartidas según nivel (global, tipo de estudio, estudio, módulo, equipo)
     *   usando claims JWT opcionales y membresía en team_members.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('user_access', function (Builder $builder) {
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $user = auth()->user();
            if (! $user instanceof JwtUser) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $userId = $user->getAuthIdentifier();

            $builder->where(function (Builder $outer) use ($user, $userId) {
                $outer->where('templates.created_by', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('template_reviewers')
                            ->whereColumn('template_reviewers.template_id', 'templates.id')
                            ->where('template_reviewers.user_id', $userId);
                    });

                $outer->orWhere(fn (Builder $shared) => self::scopeSharedTemplatesForTeacher($shared, $user, $userId));
            });
        });
    }

    /**
     * Docente: niveles compartidos (no incluye plantillas personales ajenas).
     */
    private static function scopeSharedTemplatesForTeacher(Builder $shared, JwtUser $user, string $userId): void
    {
        $shared->where(function (Builder $docente) use ($user, $userId) {
            $docente->where('templates.visibility_level', TemplateVisibilityLevel::Global->value);

            if ($user->studyTypeIds !== []) {
                $docente->orWhere(function (Builder $st) use ($user) {
                    $st->where('templates.visibility_level', TemplateVisibilityLevel::StudyType->value)
                        ->whereIn('templates.study_type_id', $user->studyTypeIds);
                });
            }

            if ($user->studyIds !== []) {
                $docente->orWhere(function (Builder $s) use ($user) {
                    $s->where('templates.visibility_level', TemplateVisibilityLevel::Study->value)
                        ->whereIn('templates.study_id', $user->studyIds);
                });
            }

            if ($user->moduleIds !== []) {
                $docente->orWhere(function (Builder $m) use ($user) {
                    $m->where('templates.visibility_level', TemplateVisibilityLevel::Module->value)
                        ->whereIn('templates.module_id', $user->moduleIds);
                });
            }

            $docente->orWhere(function (Builder $gr) use ($userId) {
                $gr->where('templates.visibility_level', TemplateVisibilityLevel::Team->value)
                    ->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('team_members')
                            ->whereColumn('team_members.team_id', 'templates.team_id')
                            ->where('team_members.user_id', $userId);
                    });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'visibility_level',
        'delivery_deadline',
        'study_type_id',
        'study_id',
        'module_id',
        'team_id',
        'created_by',
        'status',
        'version',
        'review_stages',
        'review_mode',
    ];

    protected function casts(): array
    {
        return [
            'visibility_level' => TemplateVisibilityLevel::class,
            'delivery_deadline' => 'datetime',
            'version' => 'integer',
            'review_stages' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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

    public function publishedVersions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderBy('version_number');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
