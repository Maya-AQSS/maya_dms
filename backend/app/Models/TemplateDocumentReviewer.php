<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pool de posibles revisores de documento para una plantilla.
 *
 * PK compuesta (template_id, user_id) — no hay identidad propia por fila.
 * Esta tabla define quiénes pueden ser elegidos como revisores al crear un
 * documento desde la plantilla. Se conserva `template_version_id` para trazabilidad
 * de la configuración usada en cada publicación.
 */
class TemplateDocumentReviewer extends Model
{
    public $incrementing = false;

    protected $primaryKey = ['template_id', 'user_id'];

    protected $keyType = 'string';

    protected $fillable = [
        'template_id',
        'template_version_id',
        'user_id',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }
}
