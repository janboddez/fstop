<?php

namespace App\Providers;

use App\Jobs\SendWebmention;
use App\Models\Comment;
use App\Models\Entry;
use App\Observers\CommentObserver;
use App\Observers\EntryObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Events as Eventy;

use function App\Support\ActivityPub\fetch_object;
use function App\Support\ActivityPub\fetch_profile;
use function App\Support\ActivityPub\fetch_webfinger;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') !== 'local') {
            $this->app['request']->server->set('HTTPS', 'on');
        }

        // phpcs:disable Generic.Files.LineLength.TooLong
        if (
            request()->hasCookie(config('session.cookie')) ||
            request()->is('admin*') ||
            request()->is('login')
        ) {
            $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class);
            $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Session\Middleware\StartSession::class);
            $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\View\Middleware\ShareErrorsFromSession::class);
            $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        }

        if (request()->is('indieauth*')) {
            $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class);
            $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Session\Middleware\StartSession::class);
            $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\View\Middleware\ShareErrorsFromSession::class);

            if (! request()->has('code')) {
                // Add CSRF protection only if this isn't a code exchange request from a 3rd-party IndieAuth client.
                $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
            }
        }
        // phpcs:enable Generic.Files.LineLength.TooLong

        Eventy::addAction('admin:menu', function () {
            echo Eventy::filter('admin:menu', view('admin.partials.menu')->render());
        });

        Eventy::addAction('theme:layout:head', function () {
            echo '<link rel="alternate" type="application/rss+xml" title="' . site_name() . ' &ndash; Feed" href="' .
                url('feed') . '">' . "\n";
        });

        Entry::observe(EntryObserver::class);

        // Serves a similar purpose as the `EntryObserver::saved()` method, but runs _after tags and metadata are
        // saved, too_.
        Eventy::addAction('entries:saved', function (Entry $entry) {
            // We'll be needing this later.
            $mentions = [];

            $content = $entry->content;

            // Parse for microformats. If an entry happens to be a "reply," "like" or "repost," we'd rather store that
            // information now.
            if (! preg_match('~class=("|\')?e-content("|\')?~', $content)) {
                // Ensure entry content is wrapped in `div.e-content`.
                $content = '<div class="e-content">' . $content . '</div>';
            }

            // Wrap the whole thing in `div.h-entry`, and let `php-mf2` do its thing.
            $mf = \Mf2\parse('<div class="h-entry">' . $content . '</div>', $entry->permalink);

            if (! empty($mf['items'][0]['type'][0]) && $mf['items'][0]['type'][0] === 'h-entry') {
                $hentry = $mf['items'][0];

                /**
                 * If an entry is also a reply to another web page, we'll store that page's URL in a custom field. This
                 * way, we don't have to do this on render.
                 */
                if (
                    ! empty($hentry['properties']['in-reply-to'][0]) &&
                    Str::isUrl($hentry['properties']['in-reply-to'][0], ['http', 'https'])
                ) {
                    $inReplyTo = $hentry['properties']['in-reply-to'][0];
                } elseif (
                    ! empty($hentry['properties']['in-reply-to'][0]['properties']['url']) &&
                    Str::isUrl($hentry['properties']['in-reply-to'][0]['properties']['url'][0], ['http', 'https'])
                ) {
                    $inReplyTo = $hentry['properties']['in-reply-to'][0]['properties']['url'][0];
                }

                if (! empty($inReplyTo)) {
                    $entry->meta()->updateOrCreate(
                        ['key' => '_in_reply_to'],
                        ['value' => (array) filter_var($inReplyTo, FILTER_SANITIZE_URL)]
                    );

                    // Try and see if this remote page supports ActivityPub.
                    $object = fetch_object(filter_var($inReplyTo, FILTER_SANITIZE_URL), $entry->user);
                    if (! empty($object['attributedTo']) && Str::isUrl($object['attributedTo'], ['http', 'https'])) {
                        $actor = fetch_profile(filter_var($object['attributedTo'], FILTER_SANITIZE_URL), $entry->user);
                        if (
                            ! empty($actor['username']) &&
                            ! empty($actor['url']) &&
                            Str::isUrl($actor['url'], ['http', 'https'])
                        ) {
                            /** @todo Is this even correct? */
                            $handle = '@' . filter_var(
                                $actor['username'] . '@' . parse_url($actor['url'], PHP_URL_HOST),
                                FILTER_SANITIZE_EMAIL
                            );

                            // Add to mentions.
                            $mentions[$handle] = filter_var($object['attributedTo'], FILTER_SANITIZE_URL);

                            // Store for future use (i.e., to later on determine if the remote page supports
                            // ActivityPub).
                            $entry->meta()->updateOrCreate(
                                ['key' => '_in_reply_to_author'],
                                ['value' => [$handle => filter_var($object['attributedTo'], FILTER_SANITIZE_URL)]]
                            );
                        }
                    }
                }

                /**
                 * If an entry is also a "like."
                 */
                if (
                    ! empty($hentry['properties']['like-of'][0]) &&
                    Str::isUrl($hentry['properties']['like-of'][0], ['http', 'https'])
                ) {
                    $likeOf = $hentry['properties']['like-of'][0];
                } elseif (
                    ! empty($hentry['properties']['like-of'][0]['properties']['url']) &&
                    Str::isUrl($hentry['properties']['like-of'][0]['properties']['url'][0], ['http', 'https'])
                ) {
                    $likeOf = $hentry['properties']['like-of'][0]['properties']['url'][0];
                }

                if (! empty($likeOf)) {
                    $entry->meta()->updateOrCreate(
                        ['key' => '_like_of'],
                        ['value' => (array) filter_var($likeOf, FILTER_SANITIZE_URL)]
                    );

                    // Try and see if this remote page supports ActivityPub.
                    $object = fetch_object(filter_var($likeOf, FILTER_SANITIZE_URL), $entry->user);
                    if (! empty($object['attributedTo']) && Str::isUrl($object['attributedTo'], ['http', 'https'])) {
                        $actor = fetch_profile(filter_var($object['attributedTo'], FILTER_SANITIZE_URL), $entry->user);
                        if (
                            ! empty($actor['username']) &&
                            ! empty($actor['url']) &&
                            Str::isUrl($actor['url'], ['http', 'https'])
                        ) {
                            /** @todo Is this even correct? */
                            $handle = '@' . filter_var(
                                $actor['username'] . '@' . parse_url($actor['url'], PHP_URL_HOST),
                                FILTER_SANITIZE_EMAIL
                            );

                            $mentions[$handle] = filter_var($object['attributedTo'], FILTER_SANITIZE_URL);

                            $entry->meta()->updateOrCreate(
                                ['key' => '_like_of_author'],
                                ['value' => [$handle => filter_var($object['attributedTo'], FILTER_SANITIZE_URL)]]
                            );
                        }
                    }
                }

                /**
                 * If an entry is also a "repost."
                 */
                if (
                    ! empty($hentry['properties']['repost-of'][0]) &&
                    Str::isUrl($hentry['properties']['repost-of'][0], ['http', 'https'])
                ) {
                    $repostOf = $hentry['properties']['repost-of'][0];
                } elseif (
                    ! empty($hentry['properties']['repost-of'][0]['properties']['url']) &&
                    Str::isUrl($hentry['properties']['repost-of'][0]['properties']['url'][0], ['http', 'https'])
                ) {
                    $repostOf = $hentry['properties']['repost-of'][0]['properties']['url'][0];
                }

                if (! empty($repostOf)) {
                    $entry->meta()->updateOrCreate(
                        ['key' => '_repost_of'],
                        ['value' => (array) filter_var($repostOf, FILTER_SANITIZE_URL)]
                    );

                    // Try and see if this remote page supports ActivityPub.
                    $object = fetch_object(filter_var($repostOf, FILTER_SANITIZE_URL), $entry->user);
                    if (! empty($object['attributedTo']) && Str::isUrl($object['attributedTo'], ['http', 'https'])) {
                        /** @todo Fetch @-@ handle? Add to mentions? */
                        $actor = fetch_profile(filter_var($object['attributedTo'], FILTER_SANITIZE_URL), $entry->user);
                        if (
                            ! empty($actor['username']) &&
                            ! empty($actor['url']) &&
                            Str::isUrl($actor['url'], ['http', 'https'])
                        ) {
                            /** @todo Is this even correct? */
                            $handle = '@' . filter_var(
                                $actor['username'] . '@' . parse_url($actor['url'], PHP_URL_HOST),
                                FILTER_SANITIZE_EMAIL
                            );

                            $mentions[$handle] = filter_var($object['attributedTo'], FILTER_SANITIZE_URL);

                            $entry->meta()->updateOrCreate(
                                ['key' => '_repost_of_author'],
                                ['value' => [$handle => filter_var($object['attributedTo'], FILTER_SANITIZE_URL)]]
                            );
                        }
                    }
                }
            }

            // Parse for "Fediverse" mentions, so we don't have to do this come render time.
            if (preg_match_all('~@[A-Za-z0-9\._-]+@(?:[A-Za-z0-9_-]+\.)+[A-Za-z]+~i', $content, $matches)) {
                foreach (array_unique($matches[0]) as $match) {
                    if (! $url = fetch_webfinger($match)) {
                        continue;
                    }

                    $mentions[$match] = $url;
                }
            }

            if (! empty($mentions)) {
                // Store.
                $entry->meta()->updateOrCreate(
                    ['key' => '_activitypub_mentions'],
                    ['value' => array_unique($mentions)]
                );
            }
        });

        Eventy::addAction('entries:saved', function (Entry $entry) {
            SendWebmention::dispatch($entry);
        }, PHP_INT_MAX - 1); // Just before any ActivityPub POST requests.

        Comment::observe(CommentObserver::class);

        // DB::listen(function ($query) {
        //     Log::debug(
        //         $query->sql,
        //         $query->bindings
        //     );
        // });
    }
}
