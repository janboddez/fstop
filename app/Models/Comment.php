<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    protected function authorUrl(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null) {
                if ($value) {
                    return $value;
                }

                if (! empty($this->meta['source'][0])) {
                    return $this->meta['source'][0];
                }

                return null;
            }
        )->shouldCache();
    }

    protected function source(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! empty($this->meta['source'][0])) {
                    return $this->meta['source'][0];
                }

                return null;
            }
        )->shouldCache();
    }
}
