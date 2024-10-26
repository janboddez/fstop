<?php

namespace Themes\JansTheme;

use Illuminate\Support\ServiceProvider;

class JansThemeServiceProvider extends ServiceProvider
{
    public const VERSION = '0.1.0';

    /**
     * Boot any application services.
     */
    public function boot(): void
    {
        // Allow for running, e.g., `php artisan vendor:publish --tag=public --force` to copy our `public/style.css` to
        // Laravel's `public/vendor/jans-theme/style.css`.
        /** @todo: Run this automatically each time a theme is activated? */
        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/jans-theme'),
        ], 'public');

        add_filter('theme.width', fn() => '1100');

        add_action('layout.head', function () {
            echo '<link rel="stylesheet" href="/css/app.css?v=' . config('app.version') . "\">\n";
            echo '<link rel="stylesheet" href="/vendor/jans-theme/style.css?v=' . self::VERSION . "\">\n";
        });
    }
}
