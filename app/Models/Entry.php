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

use function App\Support\ActivityPub\fetch_object;
use function App\Support\ActivityPub\fetch_profile;
use function App\Support\ActivityPub\fetch_webfinger;
use function App\Support\ActivityPub\get_inbox;

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
        'published',
    ];

    protected $casts = [
        'published' => 'datetime',
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

    public function likes(): HasMany
    {
        return $this->comments()
            ->where('type', 'like');
    }

    public function reposts(): HasMany
    {
        return $this->comments()
            ->where('type', 'repost');
    }

    public function bookmarks(): HasMany
    {
        return $this->comments()
            ->where('type', 'bookmark');
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

    protected function authorUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (Str::isUrl($this->user->url, ['http', 'https'])) {
                    return filter_var($this->user->url, FILTER_SANITIZE_URL);
                }

                return url('/');
            }
        );
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: function (?string $value = null) {
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

                $mentions = [];
                foreach ($this->mentions as $handle => $url) {
                    $mentions[$handle] = '<span class="h-card"><a href="' . e($url) .
                        '" rel="mention" class="mention u-url">' . e('@' . strtok(ltrim($handle, '@'), '@')) .
                        '</a></span>';
                }
                strtok('', '');

                if (! empty($mentions)) {
                    // Load HTML.
                    $doc = new \DOMDocument();
                    $useInternalErrors = libxml_use_internal_errors(true);
                    $doc->loadHTML(
                        mb_convert_encoding("<div>$value</div>", 'HTML-ENTITIES', 'UTF-8'), // To preserve emoji, etc.
                        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
                    );
                    $xpath = new \DOMXpath($doc);

                    // Loop over *text* nodes *not* inside an `a` or `span` element. (Don't wanna add, e.g., the
                    // `h-card` class twice.)
                    $nodes = $xpath->query('//text()[not(ancestor::a) and not(ancestor::span)]');
                    if (! empty($nodes)) {
                        foreach ($nodes as $node) {
                            $html = str_replace(
                                array_keys($mentions),
                                array_values($mentions),
                                htmlspecialchars($node->nodeValue)
                            );

                            $fragment = $doc->createDocumentFragment();
                            $fragment->appendXML($html);

                            // Replace current text node with HTML fragment.
                            $node->parentNode->replaceChild($fragment, $node);
                        }

                        $value = $doc->saveHTML();
                        libxml_use_internal_errors($useInternalErrors);

                        // Trim `<div></div>`.
                        $value = substr($value, 5);
                        $value = substr($value, 0, -7);
                    }
                }

                /**
                 * @todo Again query the resulting HTML, but this time look for `.h-card` inside `.h-cite`, and add
                 *       `.p-author` if it isn't already there.
                 */
                return trim($value);
            }
        )->shouldCache();
    }

    /**
     * @todo Cache this, like, for real.
     */
    protected function images(): Attribute
    {
        return Attribute::make(
            get: function () {
                $images = [];

                // Featured image.
                if ($this->thumbnail) {
                    $image = [
                        'type' => 'Image',
                        'url' => $this->thumbnail,
                        // Because older thumbnails may not exist as attachments.
                        'mediaType' => get_mime_type($this->thumbnail),
                    ];

                    $attachment = url_to_attachment($this->thumbnail);
                    if ($attachment) {
                        $image = array_merge($image, array_filter([
                            'name' => $attachment->alt,
                            'width' => $attachment->width,
                            'height' => $attachment->height,
                            'blurhash' => $attachment->blurhash,
                        ]));
                    }

                    $images[] = $image;
                }

                // "Attached images."
                $doc = new \DOMDocument();
                $useInternalErrors = libxml_use_internal_errors(true);
                $doc->loadHTML(
                    // Preserve emoji, etc.
                    mb_convert_encoding("<div>$this->content</div>", 'HTML-ENTITIES', 'UTF-8'),
                    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
                );
                $xpath = new \DOMXpath($doc);

                foreach ($xpath->query('//img') as $node) {
                    if (! $node->hasAttribute('src') || empty($node->getAttribute('src'))) {
                        continue;
                    }

                    $src = $node->getAttribute('src');
                    $filename = pathinfo($src, PATHINFO_FILENAME); // Filename without extension.

                    // Strip dimensions, etc., off resized images. Of course this doesn't work if the original file had
                    // these in its name.
                    /** @todo Come up with something more robust. */
                    $original = preg_replace('~-(?:\d+x\d+|scaled|rotated)$~', '', $filename);

                    $url = str_replace($filename, $original, $src); // Replace filename with the "orginal" file's name.

                    // Convert URL back to attachment.
                    $attachment = url_to_attachment($url);

                    if (! $attachment) {
                        // Not hosted here, or something else went wrong.
                        continue;
                    }

                    $image = [
                        'type' => 'Image',
                        'url' => $url,
                        'mediaType' => $attachment->mime_type,
                    ];

                    $alt = $node->hasAttribute('alt') ? $node->getAttribute('alt') : '';
                    if (! empty($alt)) {
                        $image['name'] = $alt;
                    }

                    if (count($images) < 4) {
                        $images[] = $image;

                        // There's still a risk that a featured image without alt text shows up as well as the same
                        // image with alt text as found in the post's content.
                        $images = array_unique($images);
                    }
                }

                libxml_use_internal_errors($useInternalErrors);

                return $images;
            }
        )->shouldCache();
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (?string $value = null) {
                if (empty($value)) {
                    return '';
                }

                $value = SmartyPants::defaultTransform($value, SmartyPants::ATTR_LONG_EM_DASH_SHORT_EN);

                return trim(html_entity_decode($value));
            }
        )->shouldCache();
    }

    protected function permalink(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->status !== 'published') {
                    $permalink = route(Str::plural($this->type) . '.show', [
                        'slug' => $this->slug,
                        'preview' => 'true',
                    ]);
                } else {
                    $permalink = route(Str::plural($this->type) . '.show', $this->slug);
                }

                return $permalink;
            }
        );
    }

    protected function rawName(): Attribute
    {
        return Attribute::make(
            get: function (?string $value = null, array $attributes) {
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
            get: function (?string $value = null, array $attributes) {
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

    protected function rawSummary(): Attribute
    {
        return Attribute::make(
            get: function (?string $value = null, array $attributes) {
                if (empty($attributes['summary'])) {
                    return '';
                }

                $value = $attributes['summary'];

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
            get: function (?string $value = null) {
                if (empty($value)) {
                    // Autogenerate a summary.
                    $value = strip_tags($this->content);
                    $value = Str::words($value, 30, ' […]');
                }

                return trim(html_entity_decode($value ?? '')); // Avoid double-encoded quotes, etc.
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
                        filter_var($url, FILTER_SANITIZE_URL),
                        $supportedPlatforms[parse_url($url, PHP_URL_HOST)] ?? parse_url($url, PHP_URL_HOST)
                    ),
                    $meta->value
                ));
            }
        )->shouldCache();
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->featured) {
                    return $this->featured->url;
                }

                // "Legacy" format.
                $meta = $this->meta->firstWhere('key', 'featured');

                return ! empty($meta->value[0])
                    ? $meta->value[0]
                    : null;
            }
        )->shouldCache();
    }

    /**
     * Outputs an image tag containing the entry's featured image.
     */
    protected function thumbnailHtml(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->thumbnail) {
                    return null;
                }

                if ($this->featured) {
                    $attributes = ! empty($this->featured->width)
                        ? ' width="' . $this->featured->width . '"'
                        : '';

                    $attributes .= ! empty($this->featured->height)
                    ? ' height="' . $this->featured->height . '"'
                    : '';

                    $attributes .= ! empty($this->featured->srcset)
                    ? ' srcset="' . $this->featured->srcset . '"'
                    : '';

                    $attributes .= ! empty($this->featured->blurhash)
                    ? ' data-blurhash="' . e($this->featured->blurhash) . '"'
                    : '';

                    return sprintf(
                        '<img class="u-featured" src="%s" alt="%s" loading="lazy"%s>',
                        e($this->featured->url),
                        e($this->featured->alt),
                        $attributes
                    );
                }

                return '<img class="u-featured" src="' . e($this->thumbnail) . '" alt="" loading="lazy">';
            }
        )->shouldCache();
    }

    protected function type(): Attribute
    {
        return Attribute::make(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            get: fn (?string $value = null) => ($value === null || ! in_array($value, get_registered_entry_types(), true))
                ? array_keys(self::TYPES)[0] // Treat "unsupported" types as, in this case, articles.
                : $value
        )->shouldCache();
    }

    protected function mentions(): Attribute
    {
        return Attribute::make(
            get: function (?string $value = null, array $attributes) {
                if (($meta = $this->meta->firstWhere('key', '_activitypub_mentions')) && ! empty($meta->value)) {
                    return (array) $meta->value;
                }

                $mentions = [];

                // phpcs:ignore Generic.Files.LineLength.TooLong
                if (preg_match_all('~[^\/](@[A-Za-z0-9\._-]+@(?:[A-Za-z0-9_-]+\.)+[A-Za-z]+)[^\/]~i', $attributes['content'], $matches)) {
                    foreach (array_unique($matches[1]) as $match) {
                        if (! $url = fetch_webfinger($match)) {
                            continue;
                        }

                        $meta = fetch_profile($url);
                        $mentions[$match] = $meta['url'] ?? $url;
                    }

                    if (! empty($this->id)) {
                        // Can't save a relation if we haven't yet saved the current entry.
                        $this->meta()->updateOrCreate(
                            ['key' => '_activitypub_mentions'],
                            ['value' => (array) $mentions]
                        );
                    }
                }

                return $mentions;
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

            /** @todo Limit to `e-content`? So that, for, e.g., replies, any "reply context" is left out? */
            if (
                ($inReplyTo = $this->meta->firstWhere('key', '_in_reply_to')) && ! empty($inReplyTo->value[0]) &&
                /** @todo Make the above so much smarter ... */
                preg_match('~<div.+?(?:"|\')e-content(?:"|\').*?>(.+?)</div>~s', $content, $matches)
            ) {
                $content = trim($matches[1]);

                foreach ($this->mentions as $handle => $url) {
                    // Prepend the first mention. This is tricky! (We *assume* it's the person we're replying to.)
                    $content = Str::replaceStart(
                        '<p>',
                        '<p><a href="' . e($url) . '" rel="mention">@' . e(strtok(ltrim($handle, '@'), '@')) . '</a> ',
                        $content
                    );
                    strtok('', '');

                    break;
                }
            }
        }

        $content = strip_tags($content, '<a><b><blockquote><cite><i><em><li><ol><p><pre><strong><sub><sup><ul>');
        $content = preg_replace('~<pre[^>]*>.*?</pre>(*SKIP)(*FAIL)|\r|\n|\t~s', '', $content);

        $permalink = (($meta = $this->meta->firstWhere('key', 'short_url')) && ! empty($meta->value[0]))
            ? $meta->value[0]
            : $this->permalink;

        $content .= '<p><a href="' . e($permalink) . '">' .
            e(preg_replace('~^https?://~', '', $permalink)) . '</a></p>';

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
            $to = [route('activitypub.followers', $this->user)];
            $cc = ['https://www.w3.org/ns/activitystreams#Public'];
        }

        foreach ($this->mentions as $handle => $url) {
            $tags[] = [
                'type' => 'Mention',
                'href' => $url,
                'name' => $handle,
            ];

            $cc[] = get_inbox($url);
        }

        $output = array_filter([
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                [
                    'Hashtag' => 'as:Hashtag',
                    'sensitive' => 'as:sensitive',
                ],
            ],
            'id' => strtok($this->permalink, '?'),
            'type' => 'Note',
            'attributedTo' => $this->user->author_url,
            'content' => $content,
            'contentMap' => $contentMap,
            'published' => $this->published
                ? str_replace('+00:00', 'Z', $this->published->toIso8601String())
                : str_replace('+00:00', 'Z', now()->toIso8601String()),
            'updated' => ($this->updated_at && $this->updated_at->gt($this->published))
                ? str_replace('+00:00', 'Z', $this->updated_at->toIso8601String())
                : null,
            'to' => $to ?? ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => ! empty($cc) ? $cc : [route('activitypub.followers', $this->user)],
            'tag' => ! empty($tags) ? $tags : null,
        ]);
        strtok('', '');

        if (($inReplyTo = $this->meta->firstWhere('key', '_in_reply_to')) && ! empty($inReplyTo->value[0])) {
            $object = fetch_object($inReplyTo->value[0], $this->user);
            if (! empty($object['id']) && Str::isUrl($object['id'])) {
                // This seems to be an ActivityPub object.
                $output['inReplyTo'] = filter_var($object['id'], FILTER_VALIDATE_URL);
            }

            /** @todo Add author, if they aren't already mentioned explicitly? */
        }

        if (! empty($this->images)) {
            $output['attachment'] = $this->images;
        }

        $comments = $this->comments()
            ->approved()
            ->whereHas('meta', function ($query) {
                $query->where('key', 'activitypub_url') // Or `source`?
                    ->whereNotNull('value');
            })
            ->with('entry')
            ->without('comments')
            ->paginate();

        if (! blank($comments)) {
            $output['replies'] = array_filter([
                'id' => route('activitypub.replies', $this),
                'type' => 'Collection',
                'first' => array_filter([
                    'id' => route('activitypub.replies', ['entry' => $this, 'page' => 1]),
                    'type' => 'CollectionPage',
                    'partOf' => route('activitypub.replies', $this),
                    'items' => array_filter(array_map(
                        fn ($comment) => (
                            ($meta = $comment->meta->firstWhere('key', 'activitypub_url')) &&
                            ! empty($meta->value[0])
                        )
                            ? filter_var($meta->value[0], FILTER_SANITIZE_URL)
                            : null,
                        $comments->items()
                    )),
                ]),
                // 'totalItems' => $comments->total(),
            ]);
        }

        return $output;
    }
}
