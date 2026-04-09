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
    use SoftDeletes, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('user_access', function (Builder $builder) {
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $userId = auth()->id();

            $builder->where(function (Builder $query) use ($userId) {
                $query->where('groups.owner_id', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('group_members')
                            ->whereColumn('group_members.group_id', 'groups.id')
                            ->where('group_members.user_id', $userId);
                    });
            });
        });
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }
}