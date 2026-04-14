<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'version_number',
        'blocks_snapshot',
        'changelog',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'blocks_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new AuthorizationException('Los snapshots de plantilla son inmutables.');
        });

        static::deleting(function () {
            throw new AuthorizationException('No se pueden eliminar snapshots de plantilla.');
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
