<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\DocumentReviewObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(DocumentReviewObserver::class)]
class DocumentReview extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'reviewer_id',
        'stage',
        'status',
        'rejection_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
