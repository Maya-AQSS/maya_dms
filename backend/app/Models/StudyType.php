<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyType extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    
    // We disable timestamps since FDW tables might not have them 
    public $timestamps = false;
    
    protected $fillable = ['id', 'name'];

    public function studies(): HasMany
    {
        return $this->hasMany(Study::class);
    }
}
