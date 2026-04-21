<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un módulo de curso a un usuario (identificador = claim `sub` de Keycloak o id mock FDW).
 *
 * En `local` y producción la relación lógica es la vista `user_course_modules` (FDW sobre origen remoto
 * o `user_course_modules_source` en desarrollo). En `testing` es tabla física homónima.
 */
class UserCourseModule extends Model
{
    use HasUuids;

    protected $table = 'user_course_modules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'module_id',
    ];

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id', 'id');
    }
}
