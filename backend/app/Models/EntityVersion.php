<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_version_id');
    }
}
