<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un tipo de estudio a un usuario.
 *
 * Tabla de solo lectura: en `local` y producción es la vista `user_study_types` (FDW sobre
 * origen remoto o `user_study_types_source` en desarrollo). En `testing` es tabla física homónima.
 * No admite escritura vía Eloquent.
 */
class UserStudyType extends Model
{
    protected $table = 'user_study_types';

    public $incrementing = false;

    protected $keyType = 'string';

    public function studyType(): BelongsTo
    {
        return $this->belongsTo(StudyType::class, 'study_type_id', 'id');
    }
}
