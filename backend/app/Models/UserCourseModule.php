<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un módulo de curso a un usuario.
 *
 * Tabla de solo lectura: en `local` y producción es la vista `user_course_modules` (FDW sobre
 * origen remoto o `user_course_modules_source` en desarrollo). En `testing` es tabla física homónima.
 * No admite escritura vía Eloquent.
 */
class UserCourseModule extends Model
{
    protected $table = 'user_course_modules';

    public $incrementing = false;

    protected $keyType = 'string';

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id', 'id');
    }
}
