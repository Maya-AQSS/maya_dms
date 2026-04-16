<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Group extends Model
{
    use HasUuids, SoftDeletes;

    /** @var string Tabla física {@see teams} (equipos); el modelo conserva el nombre {@see Group} por compatibilidad temporal. */
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

            $builder->where(function (Builder $query) use ($userId) {
                $query->where('teams.owner_id', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('team_members')
                            ->whereColumn('team_members.team_id', 'teams.id')
                            ->where('team_members.user_id', $userId);
                    });
            });
        });
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'team_id');
    }
}
