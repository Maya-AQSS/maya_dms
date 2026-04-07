<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'group_id',
        'user_id',
        'role',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
