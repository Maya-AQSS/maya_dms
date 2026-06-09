<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\DocumentVersionObserver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(DocumentVersionObserver::class)]
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
            'snapshot_data' => 'array',
            'version_number' => 'integer',
            'is_immutable' => 'boolean',
            'created_at' => 'datetime',
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

    public function entityVersion(): BelongsTo
    {
        return $this->belongsTo(EntityVersion::class, 'entity_version_id');
    }

    /**
     * Snapshot completo: columna legacy o, si es null, {@see EntityVersion::snapshot_data} enlazada.
     *
     * @return array<string, mixed>|null
     */
    public function resolvedSnapshotData(): ?array
    {
        if ($this->snapshot_data !== null) {
            return $this->snapshot_data;
        }

        $this->loadMissing('entityVersion');
        $entity = $this->entityVersion;

        if ($entity !== null && is_array($entity->snapshot_data)) {
            return $entity->snapshot_data;
        }

        return null;
    }
}
