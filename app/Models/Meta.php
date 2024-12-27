<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Meta extends Model
{
    public $timestamps = false;

    protected $table = 'meta';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'metable_type',
        'metable_id',
        'key',
        'value',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'value',
    ];

    /**
     * @var array<int, string>
     */
    protected $casts = [
        'value' => 'array',
    ];

    public function metable(): MorphTo
    {
        return $this->morphTo();
    }
}
