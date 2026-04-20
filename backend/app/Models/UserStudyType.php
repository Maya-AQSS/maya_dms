<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un tipo de estudio a un usuario (identificador = claim `sub` de Keycloak o id mock FDW).
 *
 * En `local` y producción la relación lógica es la vista `user_study_types` (FDW sobre origen remoto
 * o `user_study_types_source` en desarrollo). En `testing` es tabla física homónima.
 */
class UserStudyType extends Model
{
    use HasUuids;

    protected $table = 'user_study_types';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'study_type_id',
    ];

    public function studyType(): BelongsTo
    {
        return $this->belongsTo(StudyType::class, 'study_type_id', 'id');
    }
}
