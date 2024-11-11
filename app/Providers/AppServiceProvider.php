<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Entry;
use App\Models\Option;
use App\Observers\CommentObserver;
use App\Observers\EntryObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
            request()->is('admin/*') ||
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

        Eventy::addAction('admin.partials.menu', function () {
            echo Eventy::filter('admin.menu', view('admin.partials.menu')->render());
        });

        Eventy::addAction('layout.head', function () {
            echo '<link rel="alternate" type="application/rss+xml" title="' . site_name() . ' &ndash; Feed" href="' .
                url('feed') . '">' . "\n";
        });

        Entry::observe(EntryObserver::class);
        Comment::observe(CommentObserver::class);

        // DB::listen(function ($query) {
        //     Log::debug(
        //         $query->sql,
        //         $query->bindings
        //     );
        // });
    }
}
