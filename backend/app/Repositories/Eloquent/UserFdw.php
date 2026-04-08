<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de solo lectura mapeado a la vista `users` que en producción
 * lee de la foreign table `fdw_users` (postgres_fdw → Keycloak DB).
 *
 * Escenarios cubiertos:
 *   - Escenario 1: Soporte para consulta filtrada por user_id (WHERE id = ?).
 *   - Escenario 4: No expone scopes globales; toda consulta DEBE incluir filtro de usuario.
 */
class UserFdw extends Model
{
    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
