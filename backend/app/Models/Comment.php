<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'document_block_id',
        'parent_id',
        'author_id',
        'body',
        'type',
        'resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved'    => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }
}
