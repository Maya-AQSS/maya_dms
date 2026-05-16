<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Team extends Model
{
    use HasUuids, SoftDeletes;

    /** @var string Catálogo lógico de equipos (tabla o vista {@see teams}). */
    protected $table = 'teams';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'is_department',
    ];

    protected function casts(): array
    {
        return [
            'is_department' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('user_access', function (Builder $builder) {
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $userId = auth()->id();
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';

            $builder->where(function (Builder $query) use ($userId, $isPgsql) {
                if ($isPgsql) {
                    $query->whereRaw('teams.owner_id::text = ?::text', [$userId]);
                } else {
                    $query->where('teams.owner_id', $userId);
                }
                $query
                    ->orWhereExists(function ($subQuery) use ($userId, $isPgsql) {
                        $subQuery->select(DB::raw(1))
                            ->from('team_members');
                        if ($isPgsql) {
                            $subQuery->whereRaw('team_members.team_id::text = teams.id::text');
                            $subQuery->whereRaw('team_members.user_id::text = ?::text', [$userId]);
                        } else {
                            $subQuery->whereColumn('team_members.team_id', 'teams.id');
                            $subQuery->where('team_members.user_id', $userId);
                        }
                    });
            });
        });
    }

    public function newEloquentBuilder($query): Builder
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if (! $isPgsql) {
            return parent::newEloquentBuilder($query);
        }

        return new class($query) extends Builder
        {
            public function whereKey($id): static
            {
                if (is_array($id) || $id instanceof Arrayable) {
                    $ids = collect($id)->map(fn ($v) => (string) $v)->all();
                    $this->query->whereIn(
                        DB::raw($this->model->getQualifiedKeyName().'::text'),
                        $ids
                    );

                    return $this;
                }

                return $this->whereRaw(
                    $this->model->getQualifiedKeyName().'::text = ?::text',
                    [(string) $id]
                );
            }
        };
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }
}
