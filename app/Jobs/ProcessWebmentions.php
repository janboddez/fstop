<?php

namespace App\Jobs;

use App\Models\Comment;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use TorMorten\Eventy\Facades\Events as Eventy;

class ProcessWebmentions implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * If for whatever reason the job fails, the webmentions table will likely not have been updated, and the next run
     * should (eventually) pick up any unprocessed mentions anyway.
     */
    public int $tries = 1;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $webmentions = DB::select(
            'SELECT * FROM webmentions WHERE status = ? ORDER BY created_at ASC LIMIT ?',
            ['new', 5]
        );

        if (empty($webmentions)) {
            return;
        }

        foreach ($webmentions as $webmention) {
            // phpcs:ignore Generic.Files.LineLength.TooLong
            list($html, $status) = Cache::remember("html:{$webmention->source}", 60 * 60, function () use ($webmention) {
                $response = Http::withHeaders([
                        'User-Agent' => Eventy::filter(
                            'webmention:user_agent',
                            'F-Stop/' . config('app.version') . '; ' . url('/'),
                            $webmention->source
                        ),
                    ])
                    ->get($webmention->source);

                if (! $response->successful()) {
                    Log::error("[Webmention] Failed to retrieve the page at {$webmention->source}");

                    return null;
                }

                return [$response->body(), $response->status()];
            });

            if (in_array($status, [404, 410], true) || strpos($html, $webmention->target) === false) {
                // The source page no longer exists or simply does not mention our "target." Attempt to delete previous
                // mentions, if any.
                $deleted = Comment::whereHas('meta', function ($query) use ($webmention) {
                    $query->where('key', 'target')
                        ->where('value', json_encode((array) $webmention->target));
                })
                ->whereHas('meta', function ($query) use ($webmention) {
                    $query->where('key', 'source')
                        ->where('value', json_encode((array) $webmention->source));
                })
                ->delete();

                if ($deleted) {
                    Log::info("Deleted webmention for source {$webmention->source} and target {$webmention->target}");

                    DB::update(
                        'UPDATE webmentions SET status = ? WHERE id = ?',
                        ['deleted', $webmention->id]
                    );
                } else {
                    DB::update(
                        'UPDATE webmentions SET status = ? WHERE id = ?',
                        ['invalid_source', $webmention->id]
                    );
                }

                continue;
            }

            $entry = url_to_entry($webmention->target);

            if (! $entry) {
                // Target URL was removed, or does not accept webmentions.
                DB::update(
                    'UPDATE webmentions SET status = ? WHERE id = ?',
                    ['invalid_target', $webmention->id]
                );

                continue;
            }

            $data = [
                'author' => __('Anonymous'),
                'author_url' => parse_url($webmention->source, PHP_URL_SCHEME) . '://' .
                    parse_url($webmention->source, PHP_URL_HOST),
                'content' => __('&hellip; mentioned this.'),
                'status' => 'pending',
                'type' => 'mention',
                'created_at' => now(),
            ];

            // Parse in any microformats.
            $this->parseMicroformats($data, $html, $webmention);

            $comment = $entry->comments()
                ->whereHas('meta', function ($query) use ($webmention) {
                    $query->where('key', 'source')
                        ->where('value', json_encode((array) $webmention->source));
                })
                ->first();

            if ($comment) {
                Log::debug("[Webmention] Found existing reaction to {$entry->permalink} for {$webmention->source}");
                $comment->update($data);
            } else {
                Log::debug("[Webmention] Creating new reaction to {$entry->permalink} for {$webmention->source}");
                $comment = $entry->comments()->create($data);
            }

            if (! empty($data['avatar'])) {
                // Store local avatar URL in comment meta.
                $comment->meta()->updateOrCreate(
                    ['key' => 'avatar'],
                    ['value' => (array) $data['avatar']]
                );
            }

            // Store source and target in comment meta.
            $comment->meta()->updateOrCreate(
                ['key' => 'source'],
                ['value' => (array) $webmention->source]
            );

            $comment->meta()->updateOrCreate(
                ['key' => 'target'],
                ['value' => (array) $webmention->target]
            );

            DB::update(
                'UPDATE webmentions SET status = ?, updated_at = NOW() WHERE id = ?',
                [$comment->wasRecentlyCreated ? 'created' : 'updated', $webmention->id]
            );
        }
    }

    protected function parseMicroformats(array &$data, string $html, object $webmention): void
    {
        $mf = \Mf2\parse($html, $webmention->source);

        if (empty($mf['items'][0]['type'][0])) {
            // No relevant microformats found. Leave `$comment` untouched.
            return;
        }

        if ($mf['items'][0]['type'][0] === 'h-entry') {
            // Topmost item is an h-entry. Let's try to parse it.
            $this->parseHentry($data, $mf['items'][0], $webmention);

            return;
        } elseif ($mf['items'][0]['type'][0] === 'h-feed') {
            // Topmost item is an h-feed.
            if (empty($mf['items'][0]['children'])) {
                return;
            }

            if (! is_array($mf['items'][0]['children'])) {
                return;
            }

            // Loop through its children, and parse (only) the first h-entry we encounter.
            foreach ($mf['items'][0]['children'] as $child) {
                if (empty($child['type'][0])) {
                    continue;
                }

                if ($child['type'][0] !== 'h-entry') {
                    continue;
                }

                $this->parseHentry($data, $child, $webmention);

                return;
            }
        }
    }

    protected function parseHentry(array &$data, array $hentry, object $webmention): void
    {
        // Update author name.
        if (! empty($hentry['properties']['author'][0]['properties']['name'][0])) {
            $data['author'] = $hentry['properties']['author'][0]['properties']['name'][0];
        }

        // Update author URL.
        if (
            ! empty($hentry['properties']['author'][0]['properties']['url'][0]) &&
            Str::isUrl($hentry['properties']['author'][0]['properties']['url'][0], ['http', 'https'])
        ) {
            $data['author_url'] = filter_var(
                $hentry['properties']['author'][0]['properties']['url'][0],
                FILTER_SANITIZE_URL
            );
        }

        // Store author avatar, if any.
        if (
            ! empty($hentry['properties']['author'][0]['properties']['photo'][0]['value']) &&
            Str::isUrl($hentry['properties']['author'][0]['properties']['photo'][0]['value'], ['http', 'https'])
        ) {
            $avatarUrl = filter_var(
                $hentry['properties']['author'][0]['properties']['photo'][0]['value'],
                FILTER_SANITIZE_URL
            );
        } elseif (
            ! empty($hentry['properties']['author'][0]['properties']['photo'][0]) &&
            Str::isUrl($hentry['properties']['author'][0]['properties']['photo'][0], ['http', 'https'])
        ) {
            $avatarUrl = filter_var(
                $hentry['properties']['author'][0]['properties']['photo'][0],
                FILTER_SANITIZE_URL
            );
        }

        if (! empty($avatarUrl)) {
            $data['avatar'] = static::storeAvatar($avatarUrl, $data['author_url'] ?? '');
        }

        // Update comment datetime.
        if (! empty($hentry['properties']['published'][0])) {
            $data['created_at'] = (new Carbon($hentry['properties']['published'][0]));
        }

        $postType = 'mention';

        if (
            ! empty($hentry['properties']['in-reply-to']) &&
            in_array($webmention->target, (array) $hentry['properties']['in-reply-to'], true)
        ) {
            $postType = 'reply';
        }

        if (
            ! empty($hentry['properties']['repost-of']) &&
            in_array($webmention->target, (array) $hentry['properties']['repost-of'], true)
        ) {
            $postType = 'repost';
        }

        if (
            ! empty($hentry['properties']['bookmark-of']) &&
            in_array($webmention->target, (array) $hentry['properties']['bookmark-of'], true)
        ) {
            $postType = 'bookmark';
        }

        if (
            ! empty($hentry['properties']['like-of']) &&
            in_array($webmention->target, (array) $hentry['properties']['like-of'], true)
        ) {
            $postType = 'like';
        }

        // Temporarily store unaltered content.
        $content = $data['content'];

        // Overwrite default content based on post type.
        switch ($postType) {
            case 'bookmark':
                $content = '&hellip; bookmarked this!';

                break;

            case 'like':
                $content = '&hellip; liked this!';

                break;

            case 'repost':
                $content = '&hellip; reposted this!';

                break;

            case 'mention':
            case 'reply':
            default:
                if (
                    ! empty($hentry['properties']['content'][0]['html']) &&
                    ! empty($hentry['properties']['content'][0]['value']) &&
                    mb_strlen($hentry['properties']['content'][0]['value'], 'UTF-8') <= config('max_length', 500)
                ) {
                    // If the mention is short enough, store it in its entirety.
                    /** @todo Allow some tags. */
                    $content = strip_tags($hentry['properties']['content'][0]['html']);
                } elseif (! empty($hentry['properties']['content'][0]['html'])) {
                    // Fetch the bit of text surrounding the link to our page.
                    $context = $this->fetchContext($hentry['properties']['content'][0]['html'], $webmention->target);

                    if (! empty($context)) {
                        // Found context, now store it.
                        $content = $context;
                    } elseif (! empty($hentry['properties']['content'][0]['html'])) {
                        // Simply store an excerpt of the webmention source.
                        $content = Str::words(strip_tags($hentry['properties']['content'][0]['html']), 25, ' &hellip;');
                    }
                }

                break;
        }

        $data['content'] = $content;
        $data['type'] = $postType;
    }

    protected function storeAvatar(string $avatarUrl, string $authorUrl = '', int $size = 150): ?string
    {
        // Download image.
        $response = Http::withHeaders([
            'User-Agent' => Eventy::filter(
                'webmention:user_agent',
                'F-Stop/' . config('app.version') . '; ' . url('/'),
                $avatarUrl
            )])
            ->get($avatarUrl);

        if (! $response->successful()) {
            Log::warning('[Webmention] Something went wrong fetching the image at ' . $avatarUrl);

            return null;
        }

        $blob = $response->body();

        if (empty($blob)) {
            Log::warning('[Webmention] Missing image data');

            return null;
        }

        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $manager = new ImageManager(new ImagickDriver());
        } elseif (extension_loaded('gd') && function_exists('gd_info')) {
            $manager = new ImageManager(new GdDriver());
        } else {
            Log::warning('[Webmention] Imagick nor GD installed');

            return null;
        }

        // Load image.
        $image = $manager->read($blob);
        $image->cover($size, $size);

        // Generate filename.
        $hash = md5(! empty($authorUrl) ? $authorUrl : $avatarUrl);
        $relativeAvatarPath = 'webmention/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        $fullAvatarPath = Storage::disk('public')->path($relativeAvatarPath);

        if (! Storage::disk('public')->has($dir = dirname($relativeAvatarPath))) {
            // Recursively create directory if it doesn't exist, yet.
            Storage::disk('public')->makeDirectory($dir);
        }

        // Save image.
        $image->save($fullAvatarPath);

        unset($image);

        if (! file_exists($fullAvatarPath)) {
            Log::warning('[Webmention] Something went wrong saving the thumbnail');

            return null;
        }

        // Try and apply a meaningful file extension.
        $finfo = new \finfo(FILEINFO_EXTENSION);
        $extension = explode('/', $finfo->file($fullAvatarPath))[0];
        if (! empty($extension) && $extension !== '???') {
            // Rename file.
            Storage::disk('public')->move(
                $relativeAvatarPath,
                $relativeAvatarPath . ".$extension"
            );
        }

        // Return the (absolute) local avatar URL.
        return Storage::disk('public')->url($relativeAvatarPath . ".$extension");
    }

    /**
     * Looks for a link to `$target`, and returns some of the text surrounding it.
     *
     * Heavily inspired by WordPress' `wp_xmlrpc_server` class.
     *
     * @link https://github.com/WordPress/WordPress/blob/master/wp-includes/class-wp-xmlrpc-server.php.
     * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 2 or later
     *
     * @todo Convert to PHP DOM Document and so on.
     */
    protected function fetchContext(string $html, string $target): string
    {
        // Work around bug in `strip_tags()`.
        $html = str_replace('<!DOC', '<DOC', $html);
        $html = preg_replace('/[\r\n\t ]+/', ' ', $html);
        $html = preg_replace(
            '/<\/*(h1|h2|h3|h4|h5|h6|p|th|td|li|dt|dd|pre|caption|input|textarea|button|body)[^>]*>/',
            "\n\n",
            $html
        );

        // Remove all script and style tags, including their content.
        $html = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $html);
        // Just keep the tag we need.
        $html = strip_tags($html, '<a>');

        $p = explode("\n\n", $html);

        $preg_target = preg_quote($target, '|');

        foreach ($p as $para) {
            if (strpos($para, $target) !== false) {
                preg_match('|<a[^>]+?' . $preg_target . '[^>]*>([^>]+?)</a>|', $para, $context);

                if (empty($context)) {
                    // The URL isn't in a link context; keep looking.
                    continue;
                }

                // We're going to use this fake tag to mark the context in a
                // bit. The marker is needed in case the link text appears more
                // than once in the paragraph.
                $excerpt = preg_replace('|\</?wpcontext\>|', '', $para);

                // Prevent really long link text.
                if (mb_strlen($context[1]) > 100) {
                    $context[1] = mb_substr($context[1], 0, 100) . '&#8230;';
                }

                $marker = '<wpcontext>' . $context[1] . '</wpcontext>'; // Set up our marker.
                $excerpt = str_replace($context[0], $marker, $excerpt); // Swap out the link for our marker.
                $excerpt = strip_tags($excerpt, '<wpcontext>');         // Strip all tags but our context marker.
                $excerpt = trim($excerpt);
                $preg_marker = preg_quote($marker, '|');
                $excerpt = preg_replace("|.*?\s(.{0,200}$preg_marker.{0,200})\s.*|s", '$1', $excerpt);
                $excerpt = strip_tags($excerpt);

                break;
            }
        }

        if (empty($excerpt)) {
            return '';
        }

        return "[&#8230;] $excerpt [&#8230;]";
    }
}
