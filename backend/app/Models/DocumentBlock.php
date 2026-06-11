<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PurgesBlockComments;
use App\Observers\DocumentBlockObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(DocumentBlockObserver::class)]
class DocumentBlock extends Model
{
    use PurgesBlockComments;
    use SoftDeletes;

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
            'content' => 'array',
            'is_filled' => 'boolean',
            'locked_at' => 'datetime',
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

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'blockable');
    }
}
