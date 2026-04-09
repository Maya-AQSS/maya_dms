<?php

namespace App\Models;

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Template extends Model
{
    use SoftDeletes, HasUuids;

    /**
     * Visibilidad efectiva:
     * - Creador o revisor asignado.
     * - Roles con {@see JwtUser::canManageSharedTemplateVisibility()}: plantillas de su organización
     *   (organization_id NULL o igual al claim).
     * - Resto: plantillas compartidas según nivel (global + org, tipo de estudio, estudio, módulo, grupo)
     *   usando claims JWT opcionales y membresía en group_members.
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

                if ($user->canManageSharedTemplateVisibility()) {
                    $outer->orWhere(fn (Builder $coord) => self::scopeTemplatesInOrganization($coord, $user->organizationId));

                    return;
                }

                $outer->orWhere(fn (Builder $shared) => self::scopeSharedTemplatesForTeacher($shared, $user, $userId));
            });
        });
    }

    /**
     * Coordinación / dirección: todas las plantillas acotadas a la organización del usuario.
     */
    private static function scopeTemplatesInOrganization(Builder $q, ?string $userOrgId): void
    {
        if ($userOrgId !== null && $userOrgId !== '') {
            $q->where(function (Builder $inner) use ($userOrgId) {
                $inner->whereNull('templates.organization_id')
                    ->orWhere('templates.organization_id', $userOrgId);
            });

            return;
        }

        $q->whereNull('templates.organization_id');
    }

    /**
     * Docente: niveles compartidos (no incluye plantillas personales ajenas).
     */
    private static function scopeSharedTemplatesForTeacher(Builder $shared, JwtUser $user, string $userId): void
    {
        $shared->where(function (Builder $docente) use ($user, $userId) {
            $orgId = $user->organizationId;

            $docente->where(function (Builder $g) use ($orgId) {
                $g->where('templates.visibility_level', TemplateVisibilityLevel::Global->value)
                    ->where(fn (Builder $ogs) => self::scopeTemplatesInOrganization($ogs, $orgId));
            });

            if ($user->studyTypeIds !== []) {
                $docente->orWhere(function (Builder $st) use ($user, $orgId) {
                    $st->where('templates.visibility_level', TemplateVisibilityLevel::StudyType->value)
                        ->whereIn('templates.study_type_id', $user->studyTypeIds)
                        ->where(fn (Builder $ogs) => self::scopeTemplatesInOrganization($ogs, $orgId));
                });
            }

            if ($user->studyIds !== []) {
                $docente->orWhere(function (Builder $s) use ($user, $orgId) {
                    $s->where('templates.visibility_level', TemplateVisibilityLevel::Study->value)
                        ->whereIn('templates.study_id', $user->studyIds)
                        ->where(fn (Builder $ogs) => self::scopeTemplatesInOrganization($ogs, $orgId));
                });
            }

            if ($user->moduleIds !== []) {
                $docente->orWhere(function (Builder $m) use ($user, $orgId) {
                    $m->where('templates.visibility_level', TemplateVisibilityLevel::Module->value)
                        ->whereIn('templates.module_id', $user->moduleIds)
                        ->where(fn (Builder $ogs) => self::scopeTemplatesInOrganization($ogs, $orgId));
                });
            }

            $docente->orWhere(function (Builder $gr) use ($userId) {
                $gr->where('templates.visibility_level', TemplateVisibilityLevel::Group->value)
                    ->whereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('group_members')
                            ->whereColumn('group_members.group_id', 'templates.group_id')
                            ->where('group_members.user_id', $userId);
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
        'group_id',
        'organization_id',
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
            'delivery_deadline'  => 'datetime',
            'version'            => 'integer',
            'review_stages'      => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TemplateBlock::class);
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(TemplateReviewer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
