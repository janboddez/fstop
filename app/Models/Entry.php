<?php

namespace App\Models;

use App\Traits\SupportsMicropub;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use janboddez\Webmention\WebmentionSender;
use Michelf\MarkdownExtra;
use Michelf\SmartyPants;
use Symfony\Component\DomCrawler\Crawler;

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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function featured(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function scopePublic(Builder $query): void
    {
        $query->where('visibility', 'public');
    }

    public function scopeOfType(Builder $query, $type): void
    {
        $query->where('type', $type);
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

                if (request()->is('*/feed')) {
                    // Try and avoid relative links inside feeds.
                    $crawler = new Crawler($value);
                    $nodes = $crawler->filterXPath('//a[not(starts-with(@href, "http"))]');

                    if ($nodes->count() > 0) {
                        $relativeLinks = $nodes->extract(['href']);

                        foreach ($relativeLinks as $relativeLink) {
                            $value = str_replace(
                                $relativeLink,
                                WebmentionSender::absolutizeUrl($relativeLink, $this->permalink),
                                $value
                            );
                        }
                    }
                }

                if (preg_match_all('~@[A-Za-z0-9\._-]+@(?:[A-Za-z0-9_-]+\.)+[A-Za-z]+~i', $value, $matches)) {
                    foreach ($matches[0] as $match) {
                        if (! $url = activitypub_fetch_webfinger($match)) {
                            continue;
                        }

                        // We might wanna use the opportunity to store their profile.
                        $data = activitypub_fetch_profile($url);
                        $value = str_replace(
                            $match,
                            '<a href="' . e($data['url'] ?? $url) . '" rel="mention">' . e($match) . '</a>',
                            $value
                        );
                    }
                }

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

    protected function authorUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (filter_var($this->user->url, FILTER_VALIDATE_URL)) {
                    return filter_var($this->user->url, FILTER_SANITIZE_URL);
                }

                return url('/');
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

    public function serialize(): array
    {
        if (in_array($this->type, ['article', 'page'], true)) {
            $content = '<p><strong>' . e($this->name) . '</strong></p>';
            $content .= "<p>{$this->summary}</p>";
        } else {
            $content = $this->content;
        }

        $content = strip_tags($content, '<a><b><blockquote><cite><i><em><li><ol><p><pre><strong><sub><sup><ul>');
        $content = preg_replace('~<pre[^>]*>.*?</pre>(*SKIP)(*FAIL)|\r|\n|\t~s', '', $content);

        $permalink = ($meta = $this->meta->firstWhere('key', 'short_url'))
            ? $meta->value[0]
            : $this->permalink;

        $content .= '<p><a href="' . e($permalink) . '">' . e($permalink) . '</a></p>';

        $lang = strtok(app()->getLocale(), '_');
        strtok('', '');

        $contentMap = [];
        $contentMap[$lang] = $content;

        $tags = [];

        foreach ($this->tags as $tag) {
            $slug = Str::camel($tag->slug);
            $slug = ($slug !== $tag->slug)
                ? ucfirst($slug) // Also capitalize the first letter.
                : $slug;

            $tags[] = [
                'type' => 'Hashtag',
                'href' => route('tags.show', $tag->slug),
                'name' => "#$slug",
            ];
        }

        if ($this->visibility === 'public') {
            $cc = array_merge([route('activitypub.followers', $this->user)]);
        }

        if ($this->visibility === 'unlisted') {
            $to = [url("activitypub/users/{$this->user->id}/followers")];
            $cc = array_merge(['https://www.w3.org/ns/activitystreams#Public']);
        }

        if (preg_match_all('~@[A-Za-z0-9\._-]+@(?:[A-Za-z0-9_-]+\.)+[A-Za-z]+~i', $content, $matches)) {
            foreach ($matches[0] as $match) {
                if (! $url = activitypub_fetch_webfinger($match)) {
                    continue;
                }

                $tags[] = [
                    'type' => 'Mention',
                    'href' => $url,
                    'name' => $match,
                ];

                $cc[] = activitypub_get_inbox($url);
            }
        }

        $output = array_filter([
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                [
                    'Hashtag' => 'as:Hashtag',
                    'sensitive' => 'as:sensitive',
                ],
            ],
            'id' => $this->permalink,
            'type' => 'Note',
            'attributedTo' => $this->user->actor_url,
            'content' => $content,
            'contentMap' => $contentMap,
            'published' => $this->created_at
                ? str_replace('+00:00', 'Z', $this->created_at->toIso8601String())
                : str_replace('+00:00', 'Z', now()->toIso8601String()),
            'updated' => ($this->updated_at && $this->updated_at->gt($this->created_at))
                ? str_replace('+00:00', 'Z', $this->updated_at->toIso8601String())
                : null,
            'to' => $to ?? ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => ! empty($cc) ? array_unique(array_filter($cc)) : [route('activitypub.followers', $this->user)],
            'tag' => ! empty($tags) ? array_unique($tags) : null,
        ]);

        return $output;
    }
}
