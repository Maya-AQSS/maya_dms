<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_id',
        'title',
        'organization_id',
        'study_id',
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
}
