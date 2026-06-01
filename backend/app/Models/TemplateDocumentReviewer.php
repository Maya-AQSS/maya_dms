<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pool de posibles revisores de documento para una plantilla.
 *
 * PK compuesta (template_id, user_id) — no hay identidad propia por fila.
 * Esta tabla define quiénes pueden ser elegidos como revisores al crear un
 * documento desde la plantilla. El `stage` fija el orden en validación secuencial.
 *
 * NOTA: NO se ata observer aquí — Eloquent no soporta nativamente PK
 * compuestas y los events.created/updated rompen el flow. Tabla M2N de
 * pivote sin identidad propia → la auditoría relevante se cubre con el
 * observer de Template (que orquesta los reviewers via TemplateService).
 */
class TemplateDocumentReviewer extends Model
{
    public $incrementing = false;

    protected $primaryKey = ['template_id', 'user_id'];

    protected $keyType = 'string';

    protected $fillable = [
        'template_id',
        'user_id',
        'stage',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
