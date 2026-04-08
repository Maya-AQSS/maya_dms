<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Solo lectura sobre la vista `users`, que en entornos con FDW proyecta
 * la foreign table `users_fdw` (postgres_fdw → BD corporativa).
 *
 * Toda consulta debe ir filtrada por usuario (p. ej. vía UserProfileRepository).
 */
class UserFdw extends Model
{
    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [];
}
