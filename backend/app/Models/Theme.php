<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Identidad visual reutilizable que una plantilla puede aplicar a sus documentos.
 *
 * No usa entity-version snapshot (a diferencia de Template/Document). Las
 * propiedades se guardan directamente en columnas JSONB de la tabla `themes`.
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property bool $is_system
 * @property string $created_by
 * @property string|null $team_id
 * @property array<string, string> $palette
 * @property array{heading_font: string, body_font: string, base_size_pt: int, line_height: float} $typography
 * @property array{regions: array<int, mixed>, page: array<string, mixed>} $layout
 * @property array{language: string, title: ?string, subject: ?string, author: string} $accessibility
 * @property string|null $cloned_from_id
 */
class Theme extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'status',
        'is_system',
        'created_by',
        'team_id',
        'palette',
        'typography',
        'layout',
        'accessibility',
        'cloned_from_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'palette' => 'array',
            'typography' => 'array',
            'layout' => 'array',
            'accessibility' => 'array',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'theme_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    public function clones(): HasMany
    {
        return $this->hasMany(self::class, 'cloned_from_id');
    }
}
