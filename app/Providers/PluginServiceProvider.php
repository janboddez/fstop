<?php

namespace App\Providers;

use App\Models\Option;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * Load plugins.
     */
    public function boot(): void
    {
        try {
            DB::connection()
                ->getPdo();

            // Prevent code from running before migrations have run.
            if (Schema::hasTable('options')) {
                $option = Option::where('key', 'plugins')
                    ->first();

                if ($option) {
                    $plugins = $option->value;

                    foreach ($plugins as $name => $plugin) {
                        if (! $plugin['active']) {
                            continue;
                        }

                        if (empty($plugin['namespaces'])) {
                            continue;
                        }

                        if (empty($plugin['providers'])) {
                            continue;
                        }

                        // Register this plugin's namespace(s), for autoloading.
                        autoload_register($plugin['namespaces'], $plugin['dir']);

                        foreach ((array) $plugin['providers'] as $serviceProvider) {
                            // Dynamically load the plugin's service provider(s).
                            if (! class_exists($serviceProvider)) {
                                // Disable plugin.
                                $plugins[$name]['active'] = false;

                                $option->value = $plugins;
                                $option->saveQuietly();

                                break;
                            }

                            App::register($serviceProvider);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Do nothing, like when the database hasn't been set up, yet.
        }
    }
}
