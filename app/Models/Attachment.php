<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    /**
     * Predefined thumbnail sizes.
     */
    public const SIZES = [
        'large' => 1024,
        'medium_large' => 768,
        'medium' => 300,
        'thumbnail' => 150,
    ];

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

    /**
     * Always autoload meta.
     *
     * @var array
     */
    protected $with = ['meta'];

    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    public function entry()
    {
        // While an attachment can be the featured image of many entries, return but the oldest one.
        return $this->hasOne(Entry::class)->oldestOfMany();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function blurhash(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($blurhash = $this->meta->firstWhere('key', 'blurhash')) {
                    return $blurhash->value[0];
                }

                return null;
            }
        )->shouldCache();
    }

    protected function height(): Attribute
    {
        return Attribute::make(
            get: fn () => ($height = $this->meta->firstWhere('key', 'height'))
                ? (int) $height->value[0]
                : null
        )->shouldCache();
    }

    protected function large(): Attribute
    {
        return Attribute::make(
            get: fn () => ($sizes = $this->meta->firstWhere('key', 'sizes')) && isset($sizes->value['large'])
                ? Storage::disk('public')->url($sizes->value['large'])
                : $this->url // Fall back to original image.
        )->shouldCache();
    }

    protected function mimeType(): Attribute
    {
        return Attribute::make(
            get: function () {
                $path = Storage::disk('public')->path($this->path);
                $finfo = new \finfo(FILEINFO_MIME);
                $mime = $finfo->file($path);

                return $mime
                    ? trim(explode(';', $mime)[0])
                    : 'application/octet-stream';
            }
        )->shouldCache();
    }

    protected function path(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ltrim($value, '/')
        );
    }

    protected function srcset(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $sizes = $this->meta->firstWhere('key', 'sizes')) {
                    return null;
                }

                $sizes = $sizes->value;
                unset($sizes['thumbnail']); // Don't want to include a square thumbnail.

                return implode(
                    ', ',
                    array_map(
                        function ($size, $path) {
                            return Storage::disk('public')->url($path) . ' ' . static::SIZES[$size] . 'w';
                        },
                        array_keys($sizes),
                        $sizes
                    )
                );
            }
        )->shouldCache();
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn () => ($sizes = $this->meta->firstWhere('key', 'sizes')) && isset($sizes->value['thumbnail'])
                ? Storage::disk('public')->url($sizes->value['thumbnail'])
                : $this->url // Fall back to original image.
        )->shouldCache();
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk('public')->url($this->path)
        )->shouldCache();
    }

    protected function width(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($width = $this->meta->firstWhere('key', 'width')) {
                    return (int) $width->value[0];
                }

                if ($sizes = $this->meta->firstWhere('key', 'sizes')) {
                    foreach ($sizes as $key => $url) {
                        return self::SIZES[$key];
                    }
                }

                return self::SIZES['large'];
            }
        )->shouldCache();
    }
}
