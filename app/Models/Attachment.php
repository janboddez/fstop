<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasMeta;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'path',
        'name',
        'alt',
        'caption',
        'meta',
        'user_id',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public const SIZES = [
        'large' => 1024,
        'medium_large' => 768,
        'medium' => 300,
        'thumbnail' => 150,
    ];

    public function entry()
    {
        // While an attachment can be the featured image of many entries, return but the oldest (by ID) one.
        return $this->hasOne(Entry::class)->oldestOfMany();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getPathAttribute(string $value): string
    {
        return ltrim($value, '/');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getWidthAttribute(): int
    {
        if (isset($this->meta['width'][0])) {
            return (int) $this->meta['width'][0];
        }

        if (isset($this->meta['sizes'])) {
            foreach ($this->meta['sizes'] as $key => $url) {
                return self::SIZES[$key];
            }
        }

        return self::SIZES['large'];
    }

    public function getHeightAttribute(): ?int
    {
        if (isset($this->meta['height'][0])) {
            return (int) $this->meta['height'][0];
        }

        return null;
    }

    public function getThumbnailAttribute(): string
    {
        if (isset($this->meta['sizes']['thumbnail'])) {
            return Storage::disk('public')->url($this->meta['sizes']['thumbnail']);
        }

        return $this->url;
    }

    public function getLargeAttribute(): string
    {
        if (isset($this->meta['sizes']['large'])) {
            return Storage::disk('public')->url($this->meta['sizes']['large']);
        }

        return $this->url;
    }

    public function getSrcsetAttribute(): string
    {
        if (empty($this->meta['sizes'])) {
            return '';
        }

        // Exclude thumbnails.
        $sizes = $this->meta['sizes'];
        unset($sizes['thumbnail']);

        return implode(
            ', ',
            array_map(
                fn ($size, $url) => Storage::disk('public')->url($url) . ' ' . static::SIZES[$size] . 'w',
                array_keys($sizes),
                $sizes
            )
        );
    }
}
