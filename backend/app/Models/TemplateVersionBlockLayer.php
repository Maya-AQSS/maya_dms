<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Tabla pivote publicación↔bloque con payload incremental; PK compuesta (sin columna `id` propia).
 */
class TemplateVersionBlockLayer extends Pivot
{
    public $incrementing = false;

    protected $table = 'template_version_block_layers';

    protected $foreignKey = 'entity_version_id';

    protected $relatedKey = 'template_block_id';

    protected $fillable = [
        'entity_version_id',
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

    public function entityVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'entity_version_id');
    }
}
