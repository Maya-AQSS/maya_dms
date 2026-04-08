<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseModule extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;
    
    protected $fillable = ['id', 'study_id', 'name'];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }
}
