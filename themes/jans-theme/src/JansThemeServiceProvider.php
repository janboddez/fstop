<?php

namespace Themes\JansTheme;

use App\Providers\ThemeServiceProvider;
use Illuminate\Support\ServiceProvider;
use TorMorten\Eventy\Facades\Events as Eventy;

class JansThemeServiceProvider extends ServiceProvider
{
    public const VERSION = '0.1.1';

    /**
     * Boot any application services.
     */
    public function boot(): void
    {
        // Allow for running, e.g., `php artisan vendor:publish --tag=public --force` to copy our `public/style.css` to
        // Laravel's `public/vendor/jans-theme/style.css`.
        /** @todo: Run this automatically each time a theme is activated? Also we might have to run `php artisan optimize` again. */
        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/jans-theme'),
        ], 'public');

        // Something-something responsive images.
        add_filter('theme.width', fn() => '1010');

        // Remove default styles.
        // Eventy::removeAction('layout.head', [ThemeServiceProvider::class, 'printDefaultStylesheet']);

        // Load our own styles.
        // add_action('layout.head', function () {
        //     echo '<link rel="stylesheet" href="/css/default.css?v=' . config('app.version') . "\">\n";
        //     echo '<link rel="stylesheet" href="/vendor/jans-theme/style.css?v=' . self::VERSION . "\">\n";
        // });
    }
}
