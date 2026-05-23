<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockKind;
use App\Observers\TemplateBlockObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(TemplateBlockObserver::class)]
class TemplateBlock extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'title',
        'default_content',
        'description',
        'block_state',
        'sort_order',
        'kind',
    ];

    protected $attributes = [
        'kind' => BlockKind::Content->value,
    ];

    protected function casts(): array
    {
        return [
            'default_content' => 'array',
            'description' => 'array',
            'sort_order' => 'integer',
            'kind' => BlockKind::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (TemplateBlock $block): void {
            Comment::where('blockable_type', static::class)
                ->where('blockable_id', $block->id)
                ->delete();
        });
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
