<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca de lectura de un comentario por un usuario concreto.
 *
 * PK compuesta (`user_id`, `comment_id`). La persistencia irá por repositorio
 * en pasos posteriores (upsert idempotente al marcar como leído).
 */
class CommentRead extends Model
{
    protected $table = 'comment_reads';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'comment_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
