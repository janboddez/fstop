<?php

namespace App\Models;

use App\Traits\SupportsMicropub;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Michelf\MarkdownExtra;
use Michelf\SmartyPants;

class Entry extends Model
{
    use SoftDeletes;
    use SupportsMicropub;

    public const TYPES = [
        'article' => ['icon' => 'mdi mdi-newspaper-variant-outline'],
        'page' => ['icon' => 'mdi mdi-file-document-outline'],
    ];

    protected $fillable = [
        'name',
        'slug',
        'content',
        'summary',
        'status',
        'visibility',
        'type',
        'attachment_id',
        'user_id',
        'created_at',
    ];

    protected $with = ['meta'];

    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function featured()
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
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
            get: fn (string $value = null) => ($value === null || ! in_array($value, get_registered_entry_types(), true))
                ? array_keys(self::TYPES)[0] // Treat "unsupported" types as, in this case, articles.
                : $value
        )->shouldCache();
    }

    protected function permalink(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->status !== 'published') {
                    return route(Str::plural($this->type) . '.show', ['slug' => $this->id, 'preview' => 'true']);
                }

                return route(Str::plural($this->type) . '.show', $this->slug);
            }
        );
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
            get: function (string $value = null) {
                if (empty($value) && ! request()->is('admin/*') && ! app()->runningInConsole()) {
                    // Autogenerate a summary, on the front end only.
                    $value = strip_tags($this->content);
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
                $meta = $this->meta->firstWhere('key', 'featured');

                return $meta->value[0] ?? null;
            }
        );
    }

    protected function syndication(): Attribute
    {
        return Attribute::make(
            get: function () {
                $meta = $this->meta->firstWhere('key', 'syndication');

                if (empty($meta->value)) {
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
                    $meta->value
                ));
            }
        )->shouldCache();
    }
}
