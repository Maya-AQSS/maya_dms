<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockType;
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
        'block_type',
        'theme_id',
        'apply_theme',
        'title',
        'default_content',
        'description',
        'block_state',
        'page_break_after',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'block_type' => BlockType::class,
            'default_content' => 'array',
            'description' => 'array',
            'page_break_after' => 'boolean',
            'apply_theme' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id');
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
