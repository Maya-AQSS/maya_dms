<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Tabla pivote versión↔bloque con payload incremental; PK compuesta (sin columna `id` propia).
 * El trait AsPivot de Laravel usa `foreignKey` + `relatedKey` cuando no hay `id` en los atributos.
 */
class TemplateVersionBlockLayer extends Pivot
{
    public $incrementing = false;

    protected $table = 'template_version_block_layers';

    protected $foreignKey = 'template_version_id';

    protected $relatedKey = 'template_block_id';

    protected $fillable = [
        'template_version_id',
        'template_block_id',
        'sort_order',
        'inherits_from_previous_publication',
        'removed',
        'override_payload',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'inherits_from_previous_publication' => 'boolean',
            'removed' => 'boolean',
            'override_payload' => 'array',
        ];
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }
}
