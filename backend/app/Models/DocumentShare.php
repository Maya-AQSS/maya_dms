<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\DocumentShareObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(DocumentShareObserver::class)]
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
