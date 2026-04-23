<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateBlock extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'type',
        'title',
        'default_content',
        'description',
        'block_state',
        'mandatory',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_content' => 'array',
            'description'     => 'array',
            'mandatory'       => 'boolean',
            'sort_order'      => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function documentBlocks(): HasMany
    {
        return $this->hasMany(DocumentBlock::class);
    }
}
