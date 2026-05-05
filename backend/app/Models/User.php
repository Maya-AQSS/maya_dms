<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * Mapea la vista FDW 'users' → odoo.v_app_users (read-only).
 * PK = keycloak_user_id (UUID Keycloak). Sin timestamps ni password local.
 */
class User extends Model implements AuthenticatableContract, \Illuminate\Contracts\Auth\Access\Authorizable
{
    use HasFactory, Authenticatable, Authorizable;

    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'name',
        'first_name',
        'last_name',
        'username',
        'employee_id',
        'dni',
        'employee_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
