<?php

declare(strict_types=1);

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
class User extends Model implements \Illuminate\Contracts\Auth\Access\Authorizable, AuthenticatableContract
{
    use Authenticatable, Authorizable, HasFactory;

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

    /**
     * Campos PII que no deben salir en serialización accidental
     * (toArray()/toJson()). Los Resources que necesiten un campo lo acceden
     * explícitamente; esto solo evita fugas no intencionadas de PII.
     *
     * @var list<string>
     */
    protected $hidden = [
        'dni',
        'employee_id',
        'employee_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
