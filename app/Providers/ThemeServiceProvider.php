<?php

namespace App\Providers;

use App\Models\Option;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            DB::connection()
                ->getPdo();

            // Prevent code from running before migrations have run.
            if (Schema::hasTable('options')) {
                $option = Option::where('key', 'themes')
                    ->first();

                if ($option) {
                    $themes = $option->value;

                    foreach ($themes as $name => $theme) {
                        if (! $theme['active']) {
                            continue;
                        }

                        if (! empty($theme['namespaces']) && ! empty($theme['providers'])) {
                            // Register this theme's namespace(s), for autoloading.
                            autoload_register($theme['namespaces'], $theme['dir']);

                            foreach ((array) $theme['providers'] as $serviceProvider) {
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

        if (isset($name)) {
            // Let site owners override the views in `resources/views/default` with their own in `themes/*`. While views
            // are registered automatically, any assets should be made publishable through the theme's service provider.
            $views[] = __DIR__ . "/../../themes/$name/views";
        }

        $this->loadViewsFrom(array_reverse($views), 'theme');
    }
}
