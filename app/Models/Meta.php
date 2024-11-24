<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Meta extends Model
{
    public $timestamps = false;

    protected $table = 'meta';

    protected $fillable = [
        'metable_type',
        'metable_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function metable(): MorphTo
    {
        return $this->morphTo();
    }
}
