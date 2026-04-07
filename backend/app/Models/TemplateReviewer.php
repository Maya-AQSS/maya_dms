<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateReviewer extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'user_id',
        'stage',
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
}
