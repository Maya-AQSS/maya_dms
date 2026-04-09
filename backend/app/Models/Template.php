<?php

namespace App\Models;

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
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
                $query->where('templates.created_by', $userId)
                      ->orWhereExists(function ($subQuery) use ($userId) {
                          $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                   ->from('template_reviewers')
                                   ->whereColumn('template_reviewers.template_id', 'templates.id')
                                   ->where('user_id', $userId);
                      });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'visibility_level',
        'delivery_deadline',
        'study_type_id',
        'study_id',
        'module_id',
        'group_id',
        'organization_id',
        'created_by',
        'status',
        'version',
        'review_stages',
        'review_mode',
    ];

    protected function casts(): array
    {
        return [
            'visibility_level' => TemplateVisibilityLevel::class,
            'delivery_deadline'  => 'datetime',
            'version'            => 'integer',
            'review_stages'      => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TemplateBlock::class);
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(TemplateReviewer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
