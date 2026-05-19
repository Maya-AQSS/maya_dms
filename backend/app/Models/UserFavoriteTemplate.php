<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\UserFavoriteTemplateObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

use App\Repositories\Eloquent\UserFavoriteRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(UserFavoriteTemplateObserver::class)]
class UserFavoriteTemplate extends Model
{
    protected $table = 'user_favorite_templates';

    /**
     * PK compuesta (user_id, template_id). No usar save() vía Eloquent;
     * la persistencia va por {@see UserFavoriteRepository}.
     */
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'template_id',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
