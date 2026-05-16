<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TemplateReviewer extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'user_id',
        'stage',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
