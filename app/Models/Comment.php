<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Comment extends Model
{
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
        'entry_id',
        'parent_id',
        'created_at',
    ];

    protected $with = ['meta', 'comments'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable', 'metable_type', 'metable_id');
    }

    public function scopeApproved($query): void
    {
        $query->where('status', 'approved');
    }

    public function scopePending($query): void
    {
        $query->where('status', 'pending');
    }

    protected function authorUrl(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null) {
                if ($value) {
                    return $value;
                }

                // Fall back to source.
                return $this->source;
            }
        )->shouldCache();
    }

    protected function source(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'source'))
                ? $meta->value[0]
                : null
        )->shouldCache();
    }
}
