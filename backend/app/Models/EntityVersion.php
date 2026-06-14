<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\EntityVersionObserver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[ObservedBy(EntityVersionObserver::class)]
class EntityVersion extends Model
{
    use HasUuids;

    protected $table = 'entity_versions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'versionable_type',
        'versionable_id',
        'version_number',
        'base_version_id',
        'change_set',
        'status',
        'created_by',
        'published_by',
        'published_at',
        'changelog',
        'snapshot_data',
        'is_snapshot_immutable',
    ];

    protected function casts(): array
    {
        return [
            'change_set' => 'array',
            'snapshot_data' => 'array',
            'published_at' => 'datetime',
            'is_snapshot_immutable' => 'boolean',
            'version_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (EntityVersion $model) {
            // Permite transición draft → publicado (p. ej. marcar inmutable al publicar);
            // bloquea cualquier mutación si la fila ya era inmutable en BD.
            if ((bool) $model->getOriginal('is_snapshot_immutable', false)) {
                throw new AuthorizationException(__('snapshots.version_no_modify'));
            }
        });

        static::deleting(function (EntityVersion $model) {
            if ($model->is_snapshot_immutable) {
                throw new AuthorizationException(__('snapshots.version_no_delete'));
            }
        });
    }

    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_version_id');
    }

    /**
     * Filas de bloques desde {@see $snapshot_data} (publicación de plantilla o documento).
     *
     * @return list<array<string, mixed>>
     */
    public function blocksSnapshotRows(): array
    {
        if (! is_array($this->snapshot_data)) {
            return [];
        }

        $blocks = $this->snapshot_data['blocks'] ?? null;

        return is_array($blocks) ? array_values($blocks) : [];
    }
}
