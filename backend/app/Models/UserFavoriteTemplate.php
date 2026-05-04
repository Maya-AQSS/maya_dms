<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavoriteTemplate extends Model
{
    protected $table = 'user_favorite_templates';

    /** @var list<string> */
    protected $primaryKey = ['user_id', 'template_id'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'template_id',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
