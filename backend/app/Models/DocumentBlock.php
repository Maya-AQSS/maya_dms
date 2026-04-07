<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentBlock extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'template_block_id',
        'content',
        'is_filled',
        'last_edited_by',
        'locked_by',
        'locked_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content'    => 'array',
            'is_filled'  => 'boolean',
            'locked_at'  => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function templateBlock(): BelongsTo
    {
        return $this->belongsTo(TemplateBlock::class);
    }

    public function blockVersions(): HasMany
    {
        return $this->hasMany(BlockVersion::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
