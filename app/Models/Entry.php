<?php

namespace App\Models;

use App\Traits\HasMeta;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        return $this->hasMany(Comment::class)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

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

    protected function type(): Attribute
    {
        return Attribute::make(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            get: fn (string $value = null) => ($value === null || ! in_array($value, array_keys(static::getRegisteredTypes()), true))
                ? array_keys(self::TYPES)[0] // Treat "unsupported" types as, in this case, articles.
                : $value
        )->shouldCache();
    }

    protected function permalink(): Attribute
    {
        return Attribute::make(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            get: fn (string $value = null, array $attributes) => route(Str::plural($this->type) . '.show', $attributes['slug'])
        )->shouldCache();
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null) {
                if (empty($value)) {
                    return '';
                }

                $value = SmartyPants::defaultTransform($value, SmartyPants::ATTR_LONG_EM_DASH_SHORT_EN);

                return trim(html_entity_decode($value));
            }
        )->shouldCache();
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null) {
                if (empty($value)) {
                    return '';
                }

                $parser = new MarkdownExtra();
                $parser->no_markup = false; // Do not escape markup already present.

                $value = $parser->defaultTransform($value);
                $value = SmartyPants::defaultTransform($value, SmartyPants::ATTR_LONG_EM_DASH_SHORT_EN);

                $value = preg_replace('~\R~u', "\n", $value);
                $value = preg_replace('~\n\n+~u', "\n\n", $value);

                return trim($value);
            }
        )->shouldCache();
    }

    protected function rawName(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null, array $attributes) {
                $value = $attributes['name'] ?? '';

                // Replace common HTML entities with Unicode characters, for easier editing.
                $value = str_replace(
                    ['&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8211;', '&#8212;', '&#8230;', '&hellip;'],
                    ['‘', '’', '“', '”', '–', '—', '…', '…'],
                    $value
                );

                return trim($value);
            }
        );
    }

    protected function rawContent(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null, array $attributes) {
                $value = $attributes['content'] ?? '';

                // Replace common HTML entities with Unicode characters, for easier editing.
                $value = str_replace(
                    ['&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8211;', '&#8212;', '&#8230;', '&hellip;'],
                    ['‘', '’', '“', '”', '–', '—', '…', '…'],
                    $value
                );

                $value = preg_replace('~\R~u', "\n", $value);
                $value = preg_replace('~\n\n+~u', "\n\n", $value);

                return trim($value);
            }
        );
    }

    protected function summary(): Attribute
    {
        return Attribute::make(
            get: function (string $value = null, array $attributes) {
                if (empty($value) && ! request()->is('admin/*') && ! app()->runningInConsole()) {
                    // Autogenerate a summary, on the front end only.
                    // $value = strip_tags($this->content);
                    $value = strip_tags($attributes['content']);
                    $value = Str::words($value, 30, ' […]');
                }

                return html_entity_decode($value); // We encode on output!
            }
        );
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! empty($this->featured->url)) {
                    // @todo Output an actual image tag and whatnot.
                    return $this->featured->url;
                }

                // "Legacy" format.
                if (! empty($this->meta['featured'][0])) {
                    return $this->meta['featured'][0];
                }

                return null;
            }
        )->shouldCache();
    }

    protected function syndication(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->meta['syndication'])) {
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
                    $this->meta['syndication']
                ));
            }
        )->shouldCache();
    }

    protected function shortlink(): Attribute
    {
        return Attribute::make(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            get: function () {
                if (! empty($this->meta['short_url'][0])) {
                    return $this->meta['short_url'][0];
                }

                return null;
            }
        )->shouldCache();
    }

    protected function previewCard(): Attribute
    {
        return Attribute::make(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            get: function () {
                if (! empty($this->meta['preview_card']['url'])) {
                    return $this->meta['preview_card'];
                }

                return null;
            }
        )->shouldCache();
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
