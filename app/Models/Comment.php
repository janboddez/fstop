<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasMeta;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'author',
        'author_email',
        'author_url',
        'content',
        'status',
        'type',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function entry()
    {
        return $this->belongsTo(Entry::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
