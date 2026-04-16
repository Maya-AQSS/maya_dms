<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    use HasUuids;

    /** @var string Tabla física {@see team_members}. */
    protected $table = 'team_members';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'team_id');
    }
}
