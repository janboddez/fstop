<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function path(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ltrim($value, '/')
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
                $width = $this->meta->firstWhere('key', 'width');

                if ($width) {
                    return (int) $width->value[0];
                }

                if ($sizes = $this->meta->firstWhere('key', 'sizes')) {
                    foreach ($sizes as $key => $url) {
                        return self::SIZES[$key];
                    }
                }

                return self::SIZES['large'];
            }
        );
    }

    protected function height(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'height'))
                ? (int) $meta->value[0]
                : null
        );
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'sizes')) && isset($meta->value['thumbnail'])
                ? Storage::disk('public')->url($meta->value['thumbnail'])
                : $this->url
        )->shouldCache();
    }

    public function large(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'sizes')) && isset($meta->value['large'])
                ? Storage::disk('public')->url($meta->value['large'])
                : $this->url
        )->shouldCache();
    }

    public function srcset(): Attribute
    {
        return Attribute::make(
            get: function () {
                $sizes = $this->meta->firstWhere('key', 'sizes');

                if (! $sizes) {
                    return null;
                }

                $sizes = $sizes->value;

                unset($sizes['thumbnail']);

                return implode(
                    ', ',
                    array_map(
                        function ($size, $relativePath) {
                            return Storage::disk('public')->url($relativePath) . ' ' . static::SIZES[$size] . 'w';
                        },
                        array_keys($sizes),
                        $sizes
                    )
                );
            }
        )->shouldCache();
    }
}
