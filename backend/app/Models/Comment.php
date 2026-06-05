<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CommentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

use App\Support\DocumentHeadSnapshot;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[ObservedBy(CommentObserver::class)]
class Comment extends Model
{
    use HasUuids, SoftDeletes;

    private static ?bool $hasTemplateSharesTable = null;

    protected static function booted(): void
    {
        if (self::$hasTemplateSharesTable === null) {
            self::$hasTemplateSharesTable = Schema::hasTable('template_shares');
        }

        static::addGlobalScope('user_access', function (Builder $builder) {
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $userId = auth()->id();
            $builder->where(function ($query) use ($userId) {
                $query->where('comments.author_id', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('documents')
                            ->join('entity_versions as document_head_ev', 'document_head_ev.id', '=', 'documents.head_entity_version_id')
                            ->whereColumn('documents.id', 'comments.commentable_id')
                            ->where('comments.commentable_type', Document::class)
                            ->where(function ($docQuery) use ($userId) {
                                $docQuery->whereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'created_by').' = ?', [$userId])
                                    ->orWhereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('document_head_ev', 'owner_id').' = ?', [$userId])
                                    ->orWhereExists(function ($docShareQuery) use ($userId) {
                                        $docShareQuery->select(DB::raw(1))
                                            ->from('document_shares')
                                            ->whereColumn('document_shares.document_id', 'documents.id')
                                            ->where('user_id', $userId)
                                            ->where('permission', 'edit');
                                    })
                                    ->orWhereExists(function ($docReviewQuery) use ($userId) {
                                        $docReviewQuery->select(DB::raw(1))
                                            ->from('document_reviews')
                                            ->whereColumn('document_reviews.document_id', 'documents.id')
                                            ->where('reviewer_id', $userId);
                                    });
                            });
                    })
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('templates')
                            ->whereColumn('templates.id', 'comments.commentable_id')
                            ->where('comments.commentable_type', Template::class)
                            ->where(function ($templateQuery) use ($userId) {
                                $templateQuery->whereExists(function ($evQuery) use ($userId) {
                                    $evQuery->select(DB::raw(1))
                                        ->from('entity_versions')
                                        ->whereColumn('entity_versions.id', 'templates.head_entity_version_id')
                                        ->whereRaw(
                                            TemplateHeadSnapshot::jsonTemplateFieldExpression('entity_versions', 'created_by').' = ?',
                                            [$userId]
                                        );
                                })
                                    ->orWhereExists(function ($revQuery) use ($userId) {
                                        $revQuery->select(DB::raw(1))
                                            ->from('template_reviewers')
                                            ->whereColumn('template_reviewers.template_id', 'templates.id')
                                            ->where('template_reviewers.user_id', $userId);
                                    });

                                if (self::$hasTemplateSharesTable) {
                                    $templateQuery->orWhereExists(function ($shareQuery) use ($userId) {
                                        $shareQuery->select(DB::raw(1))
                                            ->from('template_shares')
                                            ->whereColumn('template_shares.template_id', 'templates.id')
                                            ->where('template_shares.user_id', $userId)
                                            ->where('template_shares.permission', 'edit');
                                    });
                                }
                            });
                    });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    public const ALLOWED_COMMENTABLE_TYPES = [
        Document::class,
        Template::class,
    ];

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'commentable_version',
        'blockable_type',
        'blockable_id',
        'parent_id',
        'author_id',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'commentable_version' => 'integer',
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

    public function edits(): HasMany
    {
        return $this->hasMany(CommentEdit::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(CommentRead::class);
    }
}
