<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
                                   ->whereColumn('documents.id', 'comments.commentable_id')
                                   ->where('comments.commentable_type', Document::class)
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
                      })
                      ->orWhereExists(function ($subQuery) use ($userId) {
                          $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                   ->from('templates')
                                   ->whereColumn('templates.id', 'comments.commentable_id')
                                   ->where('comments.commentable_type', Template::class)
                                   ->where(function ($templateQuery) use ($userId) {
                                       $templateQuery->where('templates.created_by', $userId)
                                                    ->orWhereExists(function ($revQuery) use ($userId) {
                                                        $revQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                                                 ->from('template_reviewers')
                                                                 ->whereColumn('template_reviewers.template_id', 'templates.id')
                                                                 ->where('template_reviewers.user_id', $userId);
                                                    });
                                   });
                      });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;
    public const ALLOWED_COMMENTABLE_TYPES = [
        Document::class,
        Template::class,
    ];

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'blockable_type',
        'blockable_id',
        'parent_id',
        'author_id',
        'body',
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

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
