<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'study_id',
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
            'version'       => 'integer',
            'review_stages' => 'integer',
        ];
    }
}
