<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'permission_code', 'code');
    }
}
