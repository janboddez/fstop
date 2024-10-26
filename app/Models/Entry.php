<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Michelf\MarkdownExtra;
use Michelf\SmartyPants;
use TorMorten\Eventy\Facades\Events as Eventy;

class Entry extends Model
{
    use HasMeta;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'content',
        'summary',
        'status',
        'visibility',
        'type',
        'meta',
        'attachment_id',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public const TYPES = [
        'article' => ['icon' => 'mdi mdi-newspaper-variant-outline'],
        'page' => ['icon' => 'mdi mdi-file-document-outline'],
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * An entry can have only one featured image. One image, however, can be the
     * featured image of multiple entries!
     */
    public function featured()
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    /**
     * So, an entry can have many attached images (or other documents).
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function getTypeAttribute(?string $value): string
    {
        return ($value === null || ! in_array($value, array_keys(static::getRegisteredTypes()), true))
            ? array_keys(self::TYPES())[0] // Treat "unsupported" types as, in this case, articles.
            : $value;
    }

    public function getPermalinkAttribute(): string
    {
        return route(Str::plural($this->type) . '.show', $this->slug);
    }

    public function getContentAttribute(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! request()->is('admin/*') || app()->runningInConsole()) {
            $parser = new MarkdownExtra();
            $parser->no_markup = false; // Do not escape markup already present.

            $value = $parser->defaultTransform($value);
            $value = SmartyPants::defaultTransform($value, SmartyPants::ATTR_LONG_EM_DASH_SHORT_EN);
        } else {
            // Replace common HTML entities with Unicode characters, for easier editing.
            $value = str_replace(
                ['&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8211;', '&#8212;', '&#8230;', '&hellip;'],
                ['‘', '’', '“', '”', '–', '—', '…', '…'],
                $value
            );
        }

        return trim($value);
    }

    public function getSummaryAttribute(?string $value): string
    {
        if ((! request()->is('admin/*') || app()->runningInConsole()) && empty($value)) {
            $parser = new MarkdownExtra();
            $parser->no_markup = false; // Do not escape markup already present.

            $value = $parser->defaultTransform($this->content);
            $value = trim(strip_tags($value));
            $value = Str::words($value, 30, ' […]');
        }

        return strip_tags(html_entity_decode($value, ENT_HTML5, 'UTF-8'));
    }

    public function getThumbnailAttribute(): ?string
    {
        if (! empty($this->featured->url)) {
            // @todo Output an actual image tag and whatnot.
            return $this->featured->url;
        }

        // "Legacy" format.
        $meta = $this->meta;

        if (! empty($meta['featured'][0])) {
            return $meta['featured'][0];
        }

        return null;
    }

    public function getSyndicationAttribute(): ?string
    {
        $meta = $this->meta;

        if (empty($meta['syndication'])) {
            return null;
        }

        /** @todo Move to config file. */
        $supportedPlatforms = [
            'indieweb.social' => 'Mastodon',
            'mastodon.social' => 'Mastodon',
            'geekcompass.com' => 'Mastodon',
            'pixelfed.social' => 'Pixelfed',
            'twitter.com', 'Twitter',
        ];

        return implode(', ', array_map(
            fn ($url) => sprintf(
                '<a class="u-syndication" href="%1$s">%2$s</a>',
                $url,
                $supportedPlatforms[parse_url($url, PHP_URL_HOST)] ?? parse_url($url, PHP_URL_HOST)
            ),
            $meta['syndication']
        ));
    }

    /**
     * From Laravel's `Str::slug()`.
     *
     * The one difference is that this here method allows forward slashes, too.
     */
    public static function slug(string $value, string $separator = '-', string $language = 'en'): string
    {
        $flip = $separator === '-' ? '_' : '-';

        $value = $language ? Str::ascii($value, $language) : $value;
        $value = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $value);
        $value = str_replace('@', "$separator'at'$separator", $value);

        // Allow forward slashes, too.
        // $value = preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', Str::lower($value));
        $value = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s/]+!u', '', Str::lower($value));

        $value = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $value);
        $value = trim($value, $separator);

        return $value;
    }

    public static function getRegisteredTypes(): array
    {
        /** @todo Refactor this to allow for labels, icons. */
        $types = (array) Eventy::filter('entries.registered_types', static::TYPES);

        if (! empty($types['page'])) {
            // Ensure the `page` type is listed last.
            $page = $types['page'];
            unset($types['page']);
            $types['page'] = $page;
        }

        return $types;
    }
}
