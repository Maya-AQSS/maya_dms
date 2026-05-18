<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de permisos de DMS.
 *
 * En local/prod este modelo apunta a una VIEW FDW de solo lectura (maya_auth.v_dms_permissions).
 * Intentar Permission::create() o save() fallará con error de BD — correcto por diseño.
 * En testing apunta a una tabla física poblada por PermissionsSeeder.
 */
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

}
