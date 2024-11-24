<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Entry;
use App\Observers\CommentObserver;
use App\Observers\EntryObserver;
use App\Jobs\SendWebmention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
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

        if (
            request()->hasCookie(config('session.cookie')) ||
            request()->is('admin*') ||
            request()->is('login')
        ) {
            $this->app['router']->pushMiddlewareToGroup(
                'web',
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class
            );
            $this->app['router']->pushMiddlewareToGroup(
                'web',
                \Illuminate\Session\Middleware\StartSession::class
            );
            $this->app['router']->pushMiddlewareToGroup(
                'web',
                \Illuminate\View\Middleware\ShareErrorsFromSession::class
            );
            $this->app['router']->pushMiddlewareToGroup(
                'web',
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class
            );
        }

        if (request()->is('indieauth*')) {
            $this->app['router']->pushMiddlewareToGroup(
                'web',
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class
            );
            $this->app['router']->pushMiddlewareToGroup(
                'web',
                \Illuminate\Session\Middleware\StartSession::class
            );
            $this->app['router']->pushMiddlewareToGroup(
                'web',
                \Illuminate\View\Middleware\ShareErrorsFromSession::class
            );

            if (! request()->has('code')) {
                $this->app['router']->pushMiddlewareToGroup(
                    'web',
                    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class
                );
            }
        }

        Eventy::addAction('admin:menu', function () {
            echo Eventy::filter('admin:menu', view('admin.partials.menu')->render());
        });

        Eventy::addAction('theme:layout:head', function () {
            echo '<link rel="alternate" type="application/rss+xml" title="' . site_name() . ' &ndash; Feed" href="' .
                url('feed') . '">' . "\n";
        });

        Entry::observe(EntryObserver::class);

        // Serves a similar purpose as the `EntryObserver::saved()` method, but runs *after tags and metadata are
        // saved*, too.
        /** @todo Use a "proper" event? */
        Eventy::addAction('entries:saved', function (Entry $entry) {
            SendWebmention::dispatch($entry);
        });

        Comment::observe(CommentObserver::class);

        // DB::listen(function ($query) {
        //     Log::debug(
        //         $query->sql,
        //         $query->bindings
        //     );
        // });
    }
}
