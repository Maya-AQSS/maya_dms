<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Study extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;
    
    protected $fillable = ['id', 'study_type_id', 'name'];

    public function studyType(): BelongsTo
    {
        return $this->belongsTo(StudyType::class);
    }
    
    public function courseModules(): HasMany
    {
        return $this->hasMany(CourseModule::class);
    }
}
