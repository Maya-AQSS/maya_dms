<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes, HasUuids;

    protected static function booted(): void
    {
        static::addGlobalScope('user_access', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $userId = auth()->id();
            $builder->where(function ($query) use ($userId) {
                $query->where('comments.author_id', $userId)
                      ->orWhereExists(function ($subQuery) use ($userId) {
                          $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                   ->from('documents')
                                   // Reproduces the Document's scope logic to isolate the comment via DB-level constraints
                                   ->whereColumn('documents.id', 'comments.document_id')
                                   ->where(function ($docQuery) use ($userId) {
                                       $docQuery->where('documents.created_by', $userId)
                                                ->orWhere('documents.owner_id', $userId)
                                                ->orWhereExists(function ($docShareQuery) use ($userId) {
                                                    $docShareQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                                                  ->from('document_shares')
                                                                  ->whereColumn('document_shares.document_id', 'documents.id')
                                                                  ->where('user_id', $userId);
                                                })
                                                ->orWhereExists(function ($docReviewQuery) use ($userId) {
                                                    $docReviewQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                                                   ->from('document_reviews')
                                                                   ->whereColumn('document_reviews.document_id', 'documents.id')
                                                                   ->where('reviewer_id', $userId);
                                                });
                                   });
                      });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'document_block_id',
        'template_id',
        'template_block_id',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentBlock(): BelongsTo
    {
        return $this->belongsTo(DocumentBlock::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function templateBlock(): BelongsTo
    {
        return $this->belongsTo(TemplateBlock::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
