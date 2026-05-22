<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentEdit extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'comment_id',
        'previous_body',
        'edited_by',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
