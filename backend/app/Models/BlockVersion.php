<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\BlockVersionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(BlockVersionObserver::class)]
class BlockVersion extends Model
{
    /**
     * Versión inmutable de bloque (append-only).
     * Solo tiene created_at — nunca se actualiza un registro existente.
     */
    public $timestamps = false;

    const UPDATED_AT = null;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'document_block_id',
        'document_id',
        'version_number',
        'content',
        'diff',
        'edited_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'diff' => 'array',
            'version_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function documentBlock(): BelongsTo
    {
        return $this->belongsTo(DocumentBlock::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
