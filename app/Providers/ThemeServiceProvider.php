<?php

namespace App\Providers;

use App\Models\Option;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use TorMorten\Eventy\Facades\Events as Eventy;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Load active theme.
     */
    public function boot(): void
    {
        // Add default CSS. A "theme" could remove this action.
        Eventy::addAction('layout.head', [self::class, 'printDefaultStylesheet']);

        try {
            DB::connection()
                ->getPdo();

            // Prevent code from running before migrations have run.
            if (Schema::hasTable('options')) {
                $option = Option::where('key', 'themes')
                    ->first();

                if ($option) {
                    foreach ($option->value as $theme => $attributes) {
                        if (! $attributes['active']) {
                            continue;
                        }

                        if (! empty($attributes['namespaces']) && ! empty($attributes['providers'])) {
                            // Register this theme's namespace(s), for autoloading.
                            autoload_register($attributes['namespaces'], $attributes['dir']);

                            foreach ((array) $attributes['providers'] as $serviceProvider) {
                                // Dynamically load the theme's service provider(s). This allows us to publish its
                                // assets, if any, or have themes register action and filter callbacks.
                                if (! class_exists($serviceProvider)) {
                                    continue;
                                }

                                App::register($serviceProvider);
                            }
                        }

                        break; // Only one theme can be active at a time.
                    }
                }
            }
        } catch (\Exception $e) {
            // Do nothing, like when the database hasn't been set up, yet.
        }

        // The "parent theme," if you like.
        $views = [resource_path('views/default')];

        if (! empty($theme)) {
            // Let site owners override the views in `resources/views/default` with their own in `themes/*`. While views
            // are registered automatically, any assets should be made publishable through the theme's service provider.
            $views[] = __DIR__ . "/../../themes/$theme/views";
        }

        $this->loadViewsFrom(array_reverse($views), 'theme');
    }

    public static function printDefaultStylesheet(): void
    {
        echo '<link rel="stylesheet" href="/css/default.css?v=' . config('app.version') . '">' . "\n";
    }
}
