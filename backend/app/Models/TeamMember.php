<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membresía de un usuario en un equipo.
 *
 * Tabla de solo lectura: en `local` y producción es la vista `team_members` (FDW sobre
 * origen remoto o `team_members_source` en desarrollo). En `testing` es tabla física homónima.
 * No admite escritura vía Eloquent.
 */
class TeamMember extends Model
{
    protected $table = 'team_members';

    public $incrementing = false;

    protected $keyType = 'string';

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
