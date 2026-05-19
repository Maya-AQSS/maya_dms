<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\UserFavoriteDocumentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

use App\Repositories\Eloquent\UserFavoriteRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(UserFavoriteDocumentObserver::class)]
class UserFavoriteDocument extends Model
{
    protected $table = 'user_favorite_documents';

    /**
     * PK compuesta (user_id, document_id). No usar save() vía Eloquent;
     * la persistencia va por {@see UserFavoriteRepository}.
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
