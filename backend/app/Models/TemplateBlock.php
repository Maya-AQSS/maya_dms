<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockType;
use App\Observers\TemplateBlockObserver;
use App\Support\TemplateBlockDescriptionNormalizer;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
            'page_break_after' => 'boolean',
            'apply_theme' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * La descripción se sirve como documento Tiptap (`{type:doc, content:[...]}`),
     * la forma que espera el frontend. Toleramos en LECTURA datos legados (texto
     * plano del seeder, JSON BlockNote) normalizándolos al vuelo, en lugar de
     * castear ciegamente a `array` (que devolvía `null` para texto plano y hacía
     * desaparecer el botón «ver descripción»). En ESCRITURA se persiste el doc
     * normalizado como JSON.
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ?array => TemplateBlockDescriptionNormalizer::toTiptapDoc($value),
            set: static function (mixed $value): array {
                $doc = TemplateBlockDescriptionNormalizer::toTiptapDoc($value);

                return ['description' => $doc === null ? null : json_encode($doc, JSON_UNESCAPED_UNICODE)];
            },
        );
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
