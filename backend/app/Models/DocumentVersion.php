<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
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
        'entity_version_id',
        'version_number',
        'trigger_event',
        'triggered_by',
        'snapshot_data',
        'notes',
        'is_immutable',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data'  => 'array',
            'version_number' => 'integer',
            'is_immutable'   => 'boolean',
            'created_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new AuthorizationException('Los snapshots de documento son inmutables.');
        });

        static::deleting(function () {
            throw new AuthorizationException('No se pueden eliminar snapshots de documento.');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
