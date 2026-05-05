<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavoriteDocument extends Model
{
    protected $table = 'user_favorite_documents';

    /**
     * PK compuesta (user_id, document_id). No usar save() vía Eloquent;
     * la persistencia va por {@see \App\Repositories\Eloquent\UserFavoriteRepository}.
     */
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'document_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
