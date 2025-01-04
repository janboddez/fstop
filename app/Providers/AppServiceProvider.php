<?php

namespace App\Providers;

use App\Jobs\SendWebmention;
use App\Models\Comment;
use App\Models\Entry;
use App\Observers\CommentObserver;
use App\Observers\EntryObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Events as Eventy;

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
            /**
             * Parse entry content for microformats.
             */
            $content = $entry->content;
            if (! preg_match('~class=("|\')?e-content("|\')?~', $content)) {
                $content = '<div class="e-content">' . $content . '</div>';
            }

            $mf = \Mf2\parse('<div class="h-entry">' . $content . '</div>', $entry->permalink);


            if (! empty($mf['items'][0]['type'][0]) && $mf['items'][0]['type'][0] === 'h-entry') {
                $hentry = $mf['items'][0];

                if (
                    ! empty($hentry['properties']['in-reply-to'][0]) &&
                    Str::isUrl($hentry['properties']['in-reply-to'][0], ['http', 'https'])
                ) {
                    $entry->meta()->updateOrCreate(
                        ['key' => '_in_reply_to'],
                        ['value' => (array) filter_var($hentry['properties']['in-reply-to'][0], FILTER_SANITIZE_URL)]
                    );
                } elseif (
                    ! empty($hentry['properties']['in-reply-to'][0]['properties']['url']) &&
                    Str::isUrl($hentry['properties']['in-reply-to'][0]['properties']['url'][0], ['http', 'https'])
                ) {
                    $entry->meta()->updateOrCreate(
                        ['key' => '_in_reply_to'],
                        ['value' => (array) filter_var(
                            $hentry['properties']['in-reply-to'][0]['properties']['url'][0],
                            FILTER_SANITIZE_URL
                        )]
                    );
                }

                /** @todo Add h-cite stuff. */
                if (
                    ! empty($hentry['properties']['like-of'][0]) &&
                    Str::isUrl($hentry['properties']['like-of'][0], ['http', 'https'])
                ) {
                    $entry->meta()->updateOrCreate(
                        ['key' => '_like_of'],
                        ['value' => (array) filter_var($hentry['properties']['like-of'][0], FILTER_SANITIZE_URL)]
                    );
                }

                if (
                    ! empty($hentry['properties']['repost-of'][0]) &&
                    Str::isUrl($hentry['properties']['repost-of'][0], ['http', 'https'])
                ) {
                    $entry->meta()->updateOrCreate(
                        ['key' => '_repost_of'],
                        ['value' => (array) filter_var($hentry['properties']['repost-of'][0], FILTER_SANITIZE_URL)]
                    );
                }
            }
        });

        Eventy::addAction('entries:saved', function (Entry $entry) {
            SendWebmention::dispatch($entry);
        }, PHP_INT_MAX - 1);

        Comment::observe(CommentObserver::class);

        // DB::listen(function ($query) {
        //     Log::debug(
        //         $query->sql,
        //         $query->bindings
        //     );
        // });
    }
}
