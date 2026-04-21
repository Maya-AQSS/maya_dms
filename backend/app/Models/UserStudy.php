<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un estudio a un usuario (identificador = claim `sub` de Keycloak o id mock FDW).
 *
 * En `local` y producción la relación lógica es la vista `user_studies` (FDW sobre origen remoto
 * o `user_studies_source` en desarrollo). En `testing` es tabla física homónima.
 */
class UserStudy extends Model
{
    use HasUuids;

    protected $table = 'user_studies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'study_id',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class, 'study_id', 'id');
    }
}
