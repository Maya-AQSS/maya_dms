<?php

namespace App\Models;

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
                            ->from('team_members')
                            ->whereColumn('team_members.team_id', 'teams.id');
                        if ($isPgsql) {
                            $subQuery->whereRaw('team_members.user_id::text = ?::text', [$userId]);
                        } else {
                            $subQuery->where('team_members.user_id', $userId);
                        }
                    });
            });
        });
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }
}
