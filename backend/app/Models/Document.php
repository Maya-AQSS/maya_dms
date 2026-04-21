<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        // TODO(permisos): ampliar visibilidad como en plantillas (ámbito académico
        // / equipo en JWT) y enlazar `documents.read` en policy + scope.
        static::addGlobalScope('user_access', function (Builder $builder) {
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
                        $subQuery->select(DB::raw(1))
                            ->from('document_shares')
                            ->whereColumn('document_shares.document_id', 'documents.id')
                            ->where('user_id', $userId);
                    })
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
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
        'template_version_id',
        'title',
        'study_type_id',
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
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'current_version' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
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
