<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un estudio a un usuario.
 *
 * Tabla de solo lectura: en `local` y producción es la vista `user_studies` (FDW sobre
 * origen remoto o `user_studies_source` en desarrollo). En `testing` es tabla física homónima.
 * No admite escritura vía Eloquent.
 */
class UserStudy extends Model
{
    protected $table = 'user_studies';

    public $incrementing = false;

    protected $keyType = 'string';

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class, 'study_id', 'id');
    }
}
