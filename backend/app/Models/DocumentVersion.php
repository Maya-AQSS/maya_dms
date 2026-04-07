<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    /**
     * Snapshot inmutable del documento completo (append-only).
     * Solo tiene created_at — nunca se actualiza un registro existente.
     */
    public $timestamps = false;

    const UPDATED_AT = null;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'version_number',
        'trigger_event',
        'triggered_by',
        'snapshot_data',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data'  => 'array',
            'version_number' => 'integer',
            'created_at'     => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
