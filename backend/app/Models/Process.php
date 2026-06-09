<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ProcessObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ProcessObserver::class)]
class Process extends Model
{
    protected $table = 'processes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'alias',
        'icon',
        'color',
        'description',
        'process_parent_id',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'process_parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'process_parent_id')->orderBy('code');
    }
}
