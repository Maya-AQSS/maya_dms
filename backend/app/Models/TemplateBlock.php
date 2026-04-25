<?php

namespace App\Models;

use App\Support\TemplateBlockDescriptionNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateBlock extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'title',
        'default_content',
        'description',
        'block_state',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_content' => 'array',
            'sort_order'      => 'integer',
        ];
    }

    /**
     * Descripción orientativa para revisores: siempre texto plano (se normaliza legado BlockNote/JSON al leer o al guardar).
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: static fn (?string $value): ?string => TemplateBlockDescriptionNormalizer::toPlainString($value),
            set: static fn (mixed $value): array => [
                'description' => TemplateBlockDescriptionNormalizer::toPlainString($value),
            ],
        );
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
