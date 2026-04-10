<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes, HasUuids;

    protected static function booted(): void
    {
        static::addGlobalScope('user_access', function (\Illuminate\Database\Eloquent\Builder $builder) {
            // Si no hay usuario autenticado, NO devolvemos nada (fail-closed)
            // Esto previene fugas de datos si el middleware aún no ha corrido
            if (! auth()->check()) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $userId = auth()->id();
            $builder->where(function ($query) use ($userId) {
                $query->where('documents.created_by', $userId)
                      ->orWhere('documents.owner_id', $userId)
                      ->orWhereExists(function ($subQuery) use ($userId) {
                          $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                   ->from('document_shares')
                                   ->whereColumn('document_shares.document_id', 'documents.id')
                                   ->where('user_id', $userId);
                      })
                      ->orWhereExists(function ($subQuery) use ($userId) {
                          $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                   ->from('document_reviews')
                                   ->whereColumn('document_reviews.document_id', 'documents.id')
                                   ->where('reviewer_id', $userId);
                      });
            });
        });
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'title',
        'organization_id',
        'study_id',
        'module_id',
        'created_by',
        'owner_id',
        'status',
        'current_version',
        'submitted_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at'    => 'datetime',
            'published_at'    => 'datetime',
            'current_version' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(DocumentBlock::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
