<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un permiso de catálogo a un usuario (identificador = claim `sub` de Keycloak o id mock FDW).
 *
 * En `local` y producción la relación lógica es la vista `user_permissions` (FDW sobre origen remoto
 * o `user_permissions_source` en desarrollo). En `testing` es tabla física homónima.
 */
class UserPermission extends Model
{
    use HasUuids;

    protected $table = 'user_permissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'permission_code',
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_code', 'code');
    }
}
