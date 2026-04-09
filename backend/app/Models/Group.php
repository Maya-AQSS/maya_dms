<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes, HasUuids;

    protected static function booted(): void
    {
        static::addGlobalScope('user_access', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $userId = auth()->id();
            $builder->where(function ($query) use ($userId) {
                $query->where('groups.owner_id', $userId)
                      ->orWhereExists(function ($subQuery) use ($userId) {
                          $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                   ->from('group_members')
                                   ->whereColumn('group_members.group_id', 'groups.id')
                                   ->where('user_id', $userId);
                      });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }
}
