<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentShare extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'user_id',
        'permission',
        'granted_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
