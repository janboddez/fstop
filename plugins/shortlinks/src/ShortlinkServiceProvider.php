<?php

namespace Plugins\Shortlinks;

use App\Models\Entry;
use Illuminate\Support\ServiceProvider;
use Plugins\Shortlinks\Jobs\GetShortlink;

class ShortlinkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/shortlinks.php' => config_path('shortlinks.php'),
        ]);

        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        /** todo Use a proper observer class, rather than "action hooks." */
        add_action('entries.saved', function (Entry $entry) {
            GetShortlink::dispatch($entry);
        });
    }
}
