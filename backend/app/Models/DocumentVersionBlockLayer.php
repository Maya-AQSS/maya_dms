<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Tabla pivote versión de documento↔bloque con payload incremental (PK compuesta, sin columna `id`).
 */
class DocumentVersionBlockLayer extends Pivot
{
    public $incrementing = false;

    protected $table = 'document_version_block_layers';

    protected $foreignKey = 'document_version_id';

    protected $relatedKey = 'document_block_id';

    protected $fillable = [
        'document_version_id',
        'document_block_id',
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

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
